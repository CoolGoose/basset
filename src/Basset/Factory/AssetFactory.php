<?php namespace Basset\Factory;

use Basset\Asset;
use Illuminate\Filesystem\Filesystem;

class AssetFactory extends Factory {

    /**
     * Illuminate filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Application environment.
     * 
     * @var string
     */
    protected $appEnvironment;

    /**
     * Path to the public directory.
     *
     * @var string
     */
    protected $publicPath;

    /**
     * Number of assets produced by the factory.
     * 
     * @var int
     */
    protected $assetsProduced = 0;

    /**
     * Create a new asset factory instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  string  $appEnvironment
     * @param  string  $publicPath
     * @return void
     */
    public function __construct(Filesystem $files, $appEnvironment, $publicPath)
    {
        $this->files = $files;
        $this->appEnvironment = $appEnvironment;
        $this->publicPath = $publicPath;
    }

    /**
     * Make a new asset instance.
     *
     * @param  string  $path
     * @return \Basset\Asset
     */
    public function make($path)
    {
        $absolutePath = $this->buildAbsolutePath($path);

        // If the path is determined to be outside of the public path we'll run an extra
        // check to see if we can find a published path. This will replace the original
        // absolute path.
        if ($this->outsidePublic($absolutePath))
        {
            $absolutePath = $this->findPublishedPath($path);
        }

        $relativePath = $this->buildRelativePath($absolutePath);

        $asset = new Asset($this->files, $this->factory, $this->appEnvironment, $absolutePath, $relativePath);

        return $asset->setOrder($this->nextAssetOrder());
    }

    /**
     * Find a possible published path.
     * 
     * @param  string  $path
     * @return string
     */
    protected function findPublishedPath($path)
    {
        $relativePath = $this->stripPublicPath($path);

        // Split the relative path into the different segments and filter out any empty
        // segments. These segments will then be checked until a viable possibility
        // is found and returned.
        $segments = array_filter(explode('/', $relativePath));

        for ($i = 0; $i < count($segments); ++$i)
        {
            $possiblePath = $this->publicPath.'/'.implode('/', array_slice($segments, $i));

            if ($this->files->exists($possiblePath))
            {
                return $possiblePath;
            }
        }

        return $path;
    }

    /**
     * Strip the public path from a given path.
     * 
     * @param  string  $path
     * @return string
     */
    protected function stripPublicPath($path)
    {
        $path = preg_replace('/^'.preg_quote($this->publicPath, '/').'/', '', $path);

        return str_replace(array('\\', '..'), array('/', ''), $path);
    }

    /**
     * Determines if the path is outside of the public path.
     * 
     * @param  string  $path
     * @return bool
     */
    protected function outsidePublic($path)
    {
        return ! starts_with($path, $this->publicPath);
    }

    /**
     * Build the absolute path to an asset.
     *
     * @param  string  $path
     * @return string
     */
    public function buildAbsolutePath($path)
    {
        if (is_null($path)) return $path;

        return realpath($path) ?: $path;
    }

    /**
     * Build the relative path to an asset.
     *
     * @param  string  $path
     * @return string
     */
    public function buildRelativePath($path)
    {
        if (is_null($path)) return $path;

        $relativePath = str_replace(array(realpath($this->publicPath), '\\'), array('', '/'), $path);

        // If the asset is not a remote asset then we'll trim the relative path even further to remove
        // any unnecessary leading or trailing slashes. This will leave us with a nice relative path.
        if ( ! starts_with($path, '//') and ! (bool) filter_var($path, FILTER_VALIDATE_URL))
        {
            $relativePath = trim($relativePath, '/');

            // If the given path is the same as the built relative path then the asset appears to be
            // outside of the public directory. If this is the case then we'll use an MD5 hash of
            // the assets path as the relative path to the asset.
            if (trim(str_replace('\\', '/', $path), '/') == trim($relativePath, '/'))
            {
                $path = pathinfo($path);

                $relativePath = md5($path['dirname']).'/'.$path['basename'];
            }
        }

        return $relativePath;
    }

    /**
     * Get the next asset order.
     * 
     * @return int
     */
    protected function nextAssetOrder()
    {
        return ++$this->assetsProduced;
    }

}