<?php

/*
 * This file is part of the Klipper Rope package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Rope\Repository;

use Composer\Composer;
use Composer\Json\JsonFile;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\VersionParser;
use Klipper\Rope\RecipeMeta;
use Klipper\Rope\Utils;
use Symfony\Flex\Options;
use Symfony\Flex\Recipe;

/**
 * Repository of recipes.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class PackageRecipeRepository extends AbstractRecipeRepository
{
    /**
     * @var PackageInterface
     */
    private $package;

    /**
     * @var string
     */
    private $repo;

    /**
     * @var string
     */
    private $branch;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var string
     */
    private $basePath;

    /**
     * @var VersionParser
     */
    private $versionParser;

    /**
     * @var array
     */
    private $cacheVersions = [];

    /**
     * @var array
     */
    private $cacheMap = [];

    /**
     * Constructor.
     *
     * @param Composer         $composer The composer
     * @param PackageInterface $package  The package
     * @param Options          $options  The flex options
     */
    public function __construct(Composer $composer, PackageInterface $package, Options $options)
    {
        parent::__construct($composer);

        if ($package instanceof AliasPackage) {
            $package = $package->getAliasOf();
        }

        $name = $package->getName();
        $extra = $package->getExtra();
        $this->package = $package;
        $this->options = $options;
        $base = $extra['klipper-rope-path-recipes'] ?? 'recipes';
        $this->basePath = $composer->getInstallationManager()->getInstallPath($package).'/'.trim($base, '/').'/';
        $this->versionParser = new VersionParser();

        if (!isset($extra['klipper-rope-recipes']) || true !== $extra['klipper-rope-recipes']) {
            throw new \RuntimeException(sprintf('The "%s" package is not a Rope recipe repository', $name));
        }

        $this->repo = Utils::getRecipeRepo($package);
        $this->branch = Utils::getRecipeBranch($package);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->package->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function has(PackageInterface $package): bool
    {
        return null !== $this->findRecipePath($package);
    }

    /**
     * {@inheritdoc}
     */
    public function get(PackageInterface $package, string $job): ?RecipeMeta
    {
        $recipe = null;

        if (null !== $recipePath = $this->findRecipePath($package)) {
            $json = new JsonFile($recipePath);
            $recipeBasePath = \dirname($recipePath);
            $data = $this->getRecipeData($package, basename($recipeBasePath), $json->read(), $job);
            $data = $this->findFiles($data, $recipeBasePath);
            $recipe = new RecipeMeta(new Recipe($package, $package->getName(), $job, $data), Utils::getReference($this->package));
        }

        return $recipe;
    }

    /**
     * {@inheritdoc}
     */
    protected function getRepoOrigin(PackageInterface $package): string
    {
        return $this->repo.':'.$this->branch;
    }

    /**
     * Get the cache key of package.
     *
     * @param PackageInterface $package The package
     */
    protected function getKey(PackageInterface $package): string
    {
        return $package->getName().':'.$package->getVersion();
    }

    /**
     * Get the constraint versions of package recipe.
     *
     * @param PackageInterface $package The package
     *
     * @return ConstraintInterface[]
     */
    protected function getConstraints(PackageInterface $package): array
    {
        $name = $package->getName();

        if (isset($this->cacheVersions[$name])) {
            return $this->cacheVersions[$name];
        }

        $versions = [];
        $path = $this->basePath.$name;

        if (is_dir($path) && false !== $files = scandir($path)) {
            $files = array_diff($files, ['..', '.']);

            foreach ($files as $version) {
                $versions[$version] = $this->versionParser->parseConstraints('>='.$version);
            }
        }

        return $this->cacheVersions[$name] = $versions;
    }

    /**
     * Find the path of valid recipe.
     *
     * @param PackageInterface $package The package
     */
    protected function findRecipePath(PackageInterface $package): ?string
    {
        $key = $this->getKey($package);

        if (isset($this->cacheMap[$key])) {
            return $this->cacheMap[$key];
        }

        $name = $package->getName();
        $packageVersion = $this->getVersion($package);
        $packageVersionConstraint = $this->versionParser->parseConstraints('<='.$packageVersion);
        $constraints = $this->getConstraints($package);
        $recipes = [];

        foreach ($constraints as $version => $constraint) {
            $recipePath = $this->basePath.$name.'/'.$version.'/rope.json';

            if ($packageVersionConstraint->matches($constraint) && file_exists($recipePath)) {
                $recipes[] = $recipePath;
            }
        }

        return $this->cacheMap[$key] = array_pop($recipes);
    }

    /**
     * Get the version of package.
     *
     * @param PackageInterface $package The package
     */
    protected function getVersion(PackageInterface $package): string
    {
        $version = $package->getPrettyVersion();

        if (0 === strpos($version, 'dev-') && isset($package->getExtra()['branch-alias'])) {
            $branchAliases = $package->getExtra()['branch-alias'];

            if (
                (isset($branchAliases[$version]) && $alias = $branchAliases[$version]) ||
                (isset($branchAliases['dev-master']) && $alias = $branchAliases['dev-master'])
            ) {
                $version = $alias;
            }
        }

        return $version;
    }

    /**
     * Find the files of the recipe.
     *
     * @param array  $data       The recipe data
     * @param string $recipePath The path of recipe
     */
    protected function findFiles(array $data, $recipePath): array
    {
        $manifest = $data['manifest'];

        if (isset($manifest['copy-from-recipe'])) {
            $data['files'] = $data['files'] ?? [];

            foreach ($manifest['copy-from-recipe'] as $source => $target) {
                $source = realpath($recipePath.'/'.$source);
                $target = $this->options->expandTargetDir($target);

                if ($source) {
                    $data['files'] = is_dir($source)
                        ? $this->findDir($source, $target, $data['files'])
                        : $this->findFile($source, $target, $data['files']);
                }
            }
        }

        return $data;
    }

    /**
     * Find the files of directory.
     *
     * @param string $source The source directory
     * @param string $target The target directory
     * @param array  $files  The files
     *
     * @return array The files
     */
    protected function findDir($source, $target, array $files): array
    {
        $dirIterator = new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($dirIterator);
        $baseLength = \strlen($source) + 1;

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $sourceFilename = $file->getLinkTarget();
            $targetFilename = str_replace('\\', '/', $target.substr($sourceFilename, $baseLength));
            $files = $this->findFile($sourceFilename, $targetFilename, $files);
        }

        return $files;
    }

    /**
     * Find the file.
     *
     * @param string $source The source directory
     * @param string $target The target directory
     * @param array  $files  The files
     *
     * @return array The files
     */
    protected function findFile($source, $target, array $files): array
    {
        $files[$target] = [
            'contents' => file_get_contents($source),
            'executable' => false,
        ];

        return $files;
    }
}
