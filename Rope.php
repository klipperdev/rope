<?php

/*
 * This file is part of the Klipper Rope package.
 *
 * (c) François Pluchino <francois.pluchino@klipper.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Klipper\Rope;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Klipper\Rope\Repository\InlineRecipeRepository;
use Klipper\Rope\Repository\PackageRecipeRepository;
use Klipper\Rope\Repository\RecipeRepositoryManager;
use Symfony\Flex\Event\UpdateEvent;
use Symfony\Flex\Flex;

/**
 * Composer plugin for Klipper.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class Rope implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var FlexManipulator
     */
    private $flexManipulator;

    /**
     * @var RecipeRepositoryManager
     */
    private $recipeRepoManager;

    /**
     * @var array
     */
    private $uninstalledPackages = [];

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::INIT => [['init', -PHP_INT_MAX]],
            PackageEvents::PRE_PACKAGE_UNINSTALL => [['uninstallPackage', PHP_INT_MAX]],
            ScriptEvents::POST_INSTALL_CMD => [['installRecipes', PHP_INT_MAX]],
            ScriptEvents::POST_UPDATE_CMD => [['installRecipes', PHP_INT_MAX]],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @throws \ReflectionException
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->init();
    }

    /**
     * Action on initialization of plugin.
     *
     * @throws \ReflectionException
     */
    public function init(): void
    {

        foreach ($this->composer->getPluginManager()->getPlugins() as $plugin) {
            if (FlexManipulator::isFlex($plugin)) {
                $flexManipulator = new FlexManipulator($plugin);

                if ($flexManipulator->isActivated()) {
                    $this->flexManipulator = $flexManipulator;
                    $this->recipeRepoManager = new RecipeRepositoryManager();
                }

                break;
            }
        }
    }

    /**
     * Save the inline recipe manifest of uninstalled package.
     *
     * @param PackageEvent $event The composer event
     */
    public function uninstallPackage(PackageEvent $event): void
    {
        $operation = $event->getOperation();

        if ($operation instanceof UninstallOperation) {
            $recipeMeta = $this->recipeRepoManager->getRecipe(Utils::getPackage($operation), $operation->getJobType());

            if (null !== $recipeMeta) {
                $this->uninstalledPackages[$recipeMeta->getName()][] = $recipeMeta;
            }
        }
    }

    /**
     * Action on install or update command.
     *
     * @throws \ReflectionException
     */
    public function installRecipes(Event $event): void
    {
        if (null === $this->flexManipulator) {
            $this->io->writeError('<warning>Klipper Rope has been disabled. You must enable the Symfony Flex.</warning>');

            return;
        }

        $this->registerConfigurators();
        $this->importRecipeRepositories();

        $recipeMetas = $this->fetchRecipes();
        if (empty($recipeMetas)) {
            return;
        }

        $this->io->writeError(sprintf('<info>Rope operations: %d recipe%s</info>', \count($recipeMetas), \count($recipeMetas) > 1 ? 's' : ''));
        $configurator = $this->flexManipulator->getConfigurator();
        $lock = $this->flexManipulator->getLock();
        $options = $this->flexManipulator->getOptions();
        $postInstallOutput = [];
        $manifest = null;

        foreach ($recipeMetas as $recipeMeta) {
            switch ($recipeMeta->getJob()) {
                case 'install':
                    $this->io->writeError(sprintf('  - Configuring %s', Utils::formatOrigin($recipeMeta->getOrigin())));
                    $configurator->install($recipeMeta->getRecipe(), $lock, [
                        'force' => $event instanceof UpdateEvent && $event->force(),
                    ]);
                    $manifest = $recipeMeta->getManifest();

                    if (isset($manifest['post-install-output'])) {
                        foreach ($manifest['post-install-output'] as $line) {
                            $postInstallOutput[] = $options->expandTargetDir($line);
                        }
                        $postInstallOutput[] = '';
                    }

                    break;
                case 'update':
                    break;
                case 'uninstall':
                    $this->io->writeError(sprintf('  - Unconfiguring %s', Utils::formatOrigin($recipeMeta->getOrigin())));
                    $configurator->unconfigure($recipeMeta, $lock);

                    break;
            }
        }

        if (!empty($postInstallOutput)) {
            $this->flexManipulator->addPostInstallOutput($postInstallOutput);
        }
    }

    /**
     * Fetch the recipes.
     *
     * @throws
     *
     * @return RecipeMeta[]
     */
    private function fetchRecipes(): array
    {
        $downloader = $this->flexManipulator->getDownloader();
        $operations = $this->flexManipulator->getOperations();
        /** @var OperationInterface[] $filteredFlexOperations */
        $filteredFlexOperations = [];
        /** @var RecipeMeta[] $recipeMetas */
        $recipeMetas = [];
        /** @var RecipeMeta[] $mergeRecipeMetas */
        $mergeRecipeMetas = [];
        /** @var OperationInterface[] $mergeRecipeMetaOps */
        $mergeRecipeMetaOps = [];

        foreach ($operations as $operation) {
            $fetch = $this->fetchRecipe($operation, $recipeMetas);
            /** @var null|RecipeMeta $recipeMeta */
            $recipeMeta = $fetch['recipeMeta'];

            if ($fetch['useFlexRecipe']) {
                $filteredFlexOperations[] = $operation;
            } elseif ($recipeMeta) {
                $manifest = $recipeMeta->getManifest();

                if (isset($manifest['merge-symfony-recipe']) && true === $manifest['merge-symfony-recipe']) {
                    $mergeRecipeMetas[$recipeMeta->getName()] = $recipeMeta;
                    $mergeRecipeMetaOps[$recipeMeta->getName()] = $operation;
                }
            }
        }

        if (!empty($mergeRecipeMetas)) {
            $flexRecipes = $downloader->getRecipes(array_values($mergeRecipeMetaOps));

            if (isset($flexRecipes['manifests']) && !empty($flexRecipes['manifests'])) {
                foreach ($flexRecipes['manifests'] as $name => $data) {
                    $ropeRecipe = $mergeRecipeMetas[$name]->getRecipe();
                    $ropeManifest = array_replace_recursive($data['manifest'], $ropeRecipe->getManifest());
                    $ropeFiles = $ropeRecipe->getFiles();

                    if (isset($data['files'])) {
                        $ropeFiles = array_replace_recursive($data['files'], $ropeFiles);
                    }

                    $ref = new \ReflectionClass($ropeRecipe);
                    $prop = $ref->getProperty('data');
                    $prop->setAccessible(true);

                    $recipeData = $prop->getValue($ropeRecipe);
                    $recipeData['manifest'] = $ropeManifest;
                    $recipeData['files'] = $ropeFiles;

                    $prop->setValue($ropeRecipe, $recipeData);
                }
            }
        }

        $this->flexManipulator->setOperations($filteredFlexOperations);
        $this->uninstalledPackages = [];

        return $recipeMetas;
    }

    /**
     * Fetch the recipe.
     *
     * @param OperationInterface $operation   The composer operation
     * @param RecipeMeta[]       $recipeMetas The recipes
     *
     * @return array Object with useFlexRecipe to check if the Symfony Flex recipe must be used and the recipe
     */
    private function fetchRecipe(OperationInterface $operation, array &$recipeMetas): array
    {
        $lock = $this->flexManipulator->getLock();
        $package = Utils::getPackage($operation);
        $name = $package->getName();

        if ($operation instanceof InstallOperation && $lock->has($name)) {
            return ['useFlexRecipe' => false, 'recipeMeta' => null];
        }

        $recipeMeta = $this->recipeRepoManager->getRecipe($package, $operation->getJobType());
        $useFlexRecipe = true;

        if (null === $recipeMeta && isset($this->uninstalledPackages[$name])) {
            $recipeMeta = $this->uninstalledPackages[$name];
        }

        if (null !== $recipeMeta) {
            $useFlexRecipe = false;

            // The empty manifest is only to disable the Symfony Flex recipe
            if (!empty($recipeMeta->getManifest())) {
                $recipeMetas[] = $recipeMeta;

                if ($operation instanceof InstallOperation) {
                    $lock->add($name, Utils::buildLock($recipeMeta));
                } elseif ($operation instanceof UninstallOperation) {
                    $lock->remove($name);
                }
            }
        }

        return ['useFlexRecipe' => $useFlexRecipe, 'recipeMeta' => $recipeMeta];
    }

    /**
     * Register the custom Symfony Flex Configurator.
     *
     * @throws \ReflectionException
     */
    private function registerConfigurators(): void
    {
        foreach ($this->composer->getPluginManager()->getPlugins() as $plugin) {
            if ($plugin instanceof RopePluginInterface) {
                foreach ($plugin->getRopeConfigurators() as $name => $class) {
                    $this->flexManipulator->addConfigurator($name, $class);
                }
            }
        }
    }

    /**
     * Import the recipe repositories in the manager.
     */
    private function importRecipeRepositories(): void
    {
        $options = $this->flexManipulator->getOptions();
        $packages = $this->composer->getLocker()->getLockedRepository(true)->getPackages();

        $this->recipeRepoManager->add(new InlineRecipeRepository($this->composer));

        foreach ($packages as $package) {
            $extra = $package->getExtra();

            if (isset($extra['klipper-rope-recipes']) && true === $extra['klipper-rope-recipes']) {
                $this->recipeRepoManager->add(new PackageRecipeRepository($this->composer, $package, $options));
            }
        }
    }
}
