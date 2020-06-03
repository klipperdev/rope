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

use Symfony\Flex\Recipe;

/**
 * Recipe metadata.
 *
 * @author François Pluchino <francois.pluchino@klipper.dev>
 */
class RecipeMeta
{
    private Recipe $recipe;

    private string $reference;

    /**
     * @param Recipe $recipe    The recipe
     * @param string $reference The recipe reference
     */
    public function __construct(Recipe $recipe, string $reference)
    {
        $this->recipe = $recipe;
        $this->reference = $reference;
    }

    /**
     * Get the recipe name.
     */
    public function getName(): string
    {
        return $this->recipe->getName();
    }

    /**
     * Get the recipe job.
     */
    public function getJob(): string
    {
        return $this->recipe->getJob();
    }

    /**
     * Get the recipe manifest.
     */
    public function getManifest(): array
    {
        return $this->recipe->getManifest();
    }

    /**
     * Get the recipe origin.
     */
    public function getOrigin(): string
    {
        return $this->recipe->getOrigin();
    }

    /**
     * Get the recipe files.
     */
    public function getFiles(): array
    {
        return $this->recipe->getFiles();
    }

    /**
     * Get the recipe.
     */
    public function getRecipe(): Recipe
    {
        return $this->recipe;
    }

    /**
     * Get the reference.
     */
    public function getReference(): string
    {
        return $this->reference;
    }
}
