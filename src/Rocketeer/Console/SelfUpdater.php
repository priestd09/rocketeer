<?php
namespace Rocketeer\Console;

use anlutro\cURL\cURL;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FileNotFoundException;
use Phar;
use PharException;
use Rocketeer\Traits\HasLocator;
use RuntimeException;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

class SelfUpdater
{
    use HasLocator;

    /**
     * The path to the current binary
     *
     * @type string
     */
    protected $current;

    /**
     * The version to update to
     *
     * @type string
     */
    protected $version;

    /**
     * @type cURL
     */
    protected $curl;

    /**
     * @type OutputInterface
     */
    protected $output;

    /**
     * @param Container $app
     * @param string    $current
     * @param string    $version
     */
    public function __construct(Container $app, $current, $version = null)
    {
        $this->output  = new NullOutput();
        $this->curl    = new cURL();
        $this->current = $current;
        $this->version = $version;
        $this->app     = $app;
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param cURL $curl
     */
    public function setCurl(cURL $curl)
    {
        $this->curl = $curl;
    }

    /**
     * Update Rocketeer
     *
     * @throws Exception
     * @throws FileNotFoundException
     */
    public function update()
    {
        $latest = $this->getTargetArchive();
        $folder = $this->paths->getRocketeerConfigFolder();

        $this->output->writeLn('1. Checking permissions');
        $this->checkPermissions($folder, $this->current);

        $this->output->writeLn('2. Downloading latest PHAR');
        $contents = $this->getRemoteFileContents($latest);
        if (!$contents) {
            throw new FileNotFoundException($latest);
        }

        $tempFilename = $folder.DS.basename($this->current, '.phar').'-'.($this->version ?: 'latest').'-temp.phar';
        $this->files->put($tempFilename, $contents);

        $this->output->writeLn('3. Updating Rocketeer');
        $this->updateBinary($tempFilename);
    }

    //////////////////////////////////////////////////////////////////////
    ////////////////////////////// UPDATING //////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * Get the contents of a remote file
     *
     * @param string $latest
     *
     * @return string
     */
    protected function getRemoteFileContents($latest)
    {
        $curl = $this->curl->newRequest('GET', $latest, array(
            CURLOPT_TIMEOUT => 5,
        ));

        return $curl->send();
    }

    /**
     * Update the local binary
     *
     * @param string $newFilename
     *
     * @return Exception
     * @throws Exception
     */
    protected function updateBinary($newFilename)
    {
        try {
            @chmod($newFilename, 0777 & ~umask());
            if (!ini_get('phar.readonly') && file_exists($newFilename)) {
                $phar = new Phar($newFilename);
                unset($phar);
            }
            $this->files->move($newFilename, $this->current);
        } catch (Exception $exception) {
            if (!$exception instanceof UnexpectedValueException && !$exception instanceof PharException) {
                throw $exception;
            }

            return $exception;
        }
    }

    /**
     * @param string $folder
     * @param string $localFilename
     */
    protected function checkPermissions($folder, $localFilename)
    {
        if (!$this->files->isWritable($folder)) {
            throw new RuntimeException('Temporary folder '.$folder.' used for download could not be written');
        }
        if (!$this->files->isWritable($localFilename)) {
            throw new RuntimeException('File '.$localFilename.' could not be written');
        }
    }

    //////////////////////////////////////////////////////////////////////
    ////////////////////////////// HELPERS ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * Get the path to the archive to download
     *
     * @return string
     */
    protected function getTargetArchive()
    {
        $latest = 'http://rocketeer.autopergamene.eu/versions/rocketeer';
        $latest .= $this->version ? $this->version : null;
        $latest .= '.phar';

        return $latest;
    }
}