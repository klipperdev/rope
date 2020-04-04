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

use Composer\DependencyResolver\Operation\OperationInterface;
use Symfony\Flex\Configurator;
use Symfony\Flex\Configurator\AbstractConfigurator;
use Symfony\Flex\Downloader;
use Symfony\Flex\Flex;
use Symfony\Flex\Lock;
use Symfony\Flex\Options;

/**
 * Manipulator for Flex instance.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class FlexManipulator
{
    /**
     * @var Flex
     */
    private $flex;

    /**
     * @var \ReflectionClass
     */
    private $flexRef;

    /**
     * @var null|Options
     */
    private $options;

    /**
     * @var null|Lock
     */
    private $lock;

    /**
     * @var null|Downloader
     */
    private $downloader;

    /**
     * @var null|Configurator
     */
    private $configurator;

    /**
     * @var null|\ReflectionClass
     */
    private $configuratorRef;

    /**
     * FlexManipulator constructor.
     *
     * @throws \ReflectionException
     */
    public function __construct(Flex $flex)
    {
        $this->flex = $flex;
        $this->flexRef = new \ReflectionClass($flex);
    }

    /**
     * Check if Flex is activated.
     */
    public function isActivated(): bool
    {
        return (bool) Utils::getValue($this->flexRef, $this->flex, 'activated');
    }

    /**
     * Get the Flex options.
     */
    public function getOptions(): Options
    {
        if (null === $this->options) {
            $this->options = Utils::getValue($this->flexRef, $this->flex, 'options', Options::class);
        }

        return $this->options;
    }

    /**
     * Get the Flex lock.
     */
    public function getLock(): Lock
    {
        if (null === $this->lock) {
            $this->lock = Utils::getValue($this->flexRef, $this->flex, 'lock', Lock::class);
        }

        return $this->lock;
    }

    /**
     * Get the Flex downloader.
     */
    public function getDownloader(): Downloader
    {
        if (null === $this->downloader) {
            $this->downloader = Utils::getValue($this->flexRef, $this->flex, 'downloader', Downloader::class);
        }

        return $this->downloader;
    }

    /**
     * Get the Flex lock.
     */
    public function getConfigurator(): Configurator
    {
        if (null === $this->configurator) {
            $this->configurator = Utils::getValue($this->flexRef, $this->flex, 'configurator', Configurator::class);
        }

        return $this->configurator;
    }

    /**
     * Add the configurator.
     *
     * @param string $name  The unique name of configurator
     * @param string $class The class name of configurator
     *
     * @throws \ReflectionException
     */
    public function addConfigurator(string $name, string $class): void
    {
        $ref = $this->getConfiguratorReflection();
        $configurator = $this->getConfigurator();
        $configurators = Utils::getValue($ref, $configurator, 'configurators');

        if (\array_key_exists($name, $configurators)) {
            throw new \RuntimeException(sprintf('The custom configurator of the Symfony Flex with the name "%s" and the class "%s" already exist', $name, $class));
        }

        if (!is_a($class, AbstractConfigurator::class, true)) {
            throw new \RuntimeException(sprintf('The custom configurator "%s" of Symfony Flex must extend the "Symfony\Flex\Configurator\AbstractConfigurator" class', $class));
        }

        $configurators[$name] = $class;
        Utils::setValue($ref, $configurator, 'configurators', $configurators);
    }

    /**
     * Add the message in the post install output.
     *
     * @param string[] $postInstallOutput The message
     */
    public function addPostInstallOutput(array $postInstallOutput): void
    {
        $postInstallOutput = array_merge(
            Utils::getValue($this->flexRef, $this->flex, 'postInstallOutput'),
            $postInstallOutput
        );

        Utils::setValue($this->flexRef, $this->flex, 'postInstallOutput', $postInstallOutput);
    }

    /**
     * Get the operations for Flex.
     *
     * @return OperationInterface[]
     */
    public function getOperations(): array
    {
        return Utils::getValue($this->flexRef, $this->flex, 'operations');
    }

    /**
     * Set the operation for Flex.
     *
     * @param OperationInterface[] $operations The operations
     */
    public function setOperations(array $operations): void
    {
        Utils::setValue($this->flexRef, $this->flex, 'operations', $operations);
    }

    /**
     * Get the reflection class of configurator.
     *
     * @throws \ReflectionException
     */
    private function getConfiguratorReflection(): \ReflectionClass
    {
        if (null === $this->configuratorRef) {
            $this->configuratorRef = new \ReflectionClass($this->getConfigurator());
        }

        return $this->configuratorRef;
    }
}
