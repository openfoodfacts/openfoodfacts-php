<?php

declare(strict_types=1);

namespace OpenFoodFacts\Models;

final class Nutrients
{
    private string $id;
    private ?string $name;
    private bool $important;
    private bool $displayInEditForm;
    private array $children;

    /**
     * Nutrients constructor
     */
    public function __construct(string $id, ?string $name, bool $important, bool $displayInEditForm, array $children)
    {
        $this->id = $id;
        $this->name = $name;
        $this->important = $important;
        $this->displayInEditForm = $displayInEditForm;
        $this->children = $children;
    }


    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isImportant(): bool
    {
        return $this->important;
    }

    public function isDisplayInEditForm(): bool
    {
        return $this->displayInEditForm;
    }

    /**
     * @return Nutrients[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public static function createFromArray(array $data): Nutrients
    {
        return new Nutrients(
            $data['id'],
            $data['name'] ?? null,
            $data['important'],
            $data['display_in_edit_form'],
            array_map(
                fn ($subLine) => Nutrients::createFromArray($subLine),
                $data['nutrients'] ?? []
            )
        );
    }
}
