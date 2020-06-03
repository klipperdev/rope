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

use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\OperationInterface;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\Package\PackageInterface;

/**
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
abstract class Utils
{
    /**
     * Get the value of the object property.
     *
     * @param \ReflectionClass $ref        The reflection class
     * @param object           $object     The object
     * @param string           $property   The property name
     * @param null|string      $instanceOf The class name
     *
     * @throws
     *
     * @return mixed
     */
    public static function getValue(\ReflectionClass $ref, object $object, string $property, ?string $instanceOf = null)
    {
        $propertyRef = $ref->getProperty($property);
        $propertyRef->setAccessible(true);
        $value = $propertyRef->getValue($object);
        $propertyRef->setAccessible(false);

        if (null !== $instanceOf && !is_a($value, $instanceOf, true)) {
            throw new \RuntimeException(sprintf(
                'The "%s" property of Flex must be an instance of "%s"',
                $property,
                $instanceOf
            ));
        }

        return $value;
    }

    /**
     * @param \ReflectionClass $ref      The reflection class
     * @param object           $object   The object
     * @param string           $property The property name
     * @param mixed            $value    The value
     *
     * @throws
     */
    public static function setValue(\ReflectionClass $ref, object $object, string $property, $value): void
    {
        $propertyRef = $ref->getProperty($property);
        $propertyRef->setAccessible(true);
        $propertyRef->setValue($object, $value);
        $propertyRef->setAccessible(false);
    }

    /**
     * Get the package of operation.
     *
     * @param InstallOperation|OperationInterface|UninstallOperation|UpdateOperation $operation The operation
     */
    public static function getPackage(OperationInterface $operation): PackageInterface
    {
        if ($operation instanceof UpdateOperation) {
            return $operation->getTargetPackage();
        }

        return $operation->getPackage();
    }

    /**
     * Format the recipe origin.
     *
     * @param string $origin The recipe origin
     */
    public static function formatOrigin(string $origin): string
    {
        if (!preg_match('/^([^\:]+?)\:([^\@]+)@(.+)$/', $origin, $matches)) {
            return $origin;
        }

        return sprintf('<info>%s</info> (<comment>>=%s</comment>): From %s', $matches[1], $matches[2], 'klipper-rope recipe' === $matches[3] ? '<comment>'.$matches[3].'</comment>' : $matches[3]);
    }

    /**
     * Get the parts of origin.
     *
     * @param string $origin The origin
     */
    public static function getOriginParts(string $origin): ?array
    {
        $origins = null;

        if (preg_match('/^([^\:]+?)\:([^\@]+)@(.+)$/', $origin, $matches)) {
            $branchPos = strpos($matches[3], ':');
            $origins = [
                'package' => $matches[1],
                'version' => $matches[2],
                'repo' => false !== $branchPos ? substr($matches[3], 0, $branchPos) : $matches[3],
                'branch' => false !== $branchPos ? substr($matches[3], $branchPos + 1) : null,
            ];
        }

        return $origins;
    }

    /**
     * Get the repo of recipe.
     *
     * @param PackageInterface $package The package
     */
    public static function getRecipeRepo(PackageInterface $package): string
    {
        $url = $package->getSourceUrl() ?? $package->getDistUrl();
        $host = parse_url($url, PHP_URL_HOST);

        return ($host ?: 'packages').'/'.$package->getName();
    }

    /**
     * Get the repo branch of recipe.
     *
     * @param PackageInterface $package The package
     */
    public static function getRecipeBranch(PackageInterface $package): string
    {
        return str_replace('dev-', '', $package->getPrettyVersion());
    }

    /**
     * Get the reference.
     *
     * @param PackageInterface $package The package
     */
    public static function getReference(PackageInterface $package): string
    {
        return $package->getSourceReference() ?: $package->getDistReference();
    }

    /**
     * Get the lock informations.
     *
     * @param RecipeMeta $recipeMeta The recipe meta
     */
    public static function buildLock(RecipeMeta $recipeMeta): array
    {
        $originParts = self::getOriginParts($recipeMeta->getOrigin());
        $version = $recipeMeta->getRecipe()->getPackage()->getPrettyVersion();
        $version = $originParts && $originParts['version'] ? $originParts['version'] : $version;

        return [
            'version' => $version,
            'recipe' => [
                'repo' => $originParts ? $originParts['repo'] : 'klipper-rope recipe',
                'branch' => $originParts && $originParts['branch'] ? $originParts['branch'] : 'master',
                'version' => $version,
                'ref' => $recipeMeta->getReference(),
            ],
        ];
    }
}
