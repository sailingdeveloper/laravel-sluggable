<?php

namespace Spatie\Sluggable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

trait HasSlug
{
    /** @var \Spatie\Sluggable\SlugOptions */
    protected $slugOptions;

    /**
     * Get the options for generating the slug.
     */
    abstract public function getSlugOptions(): SlugOptions;

    /**
     * Boot the trait.
     */
    protected static function bootHasSlug()
    {
        static::creating(function (Model $model) {
            $model->addSlug();
        });

        static::updating(function (Model $model) {
            $model->addSlug();
        });
    }

    /**
     * Add the slug to the model.
     */
    protected function addSlug()
    {
        $this->slugOptions = $this->getSlugOptions();

        $this->guardAgainstInvalidSlugOptions();

        $slug = $this->generateNonUniqueSlug();

        if ($this->slugOptions->generateUniqueSlugs) {
            $slug = $this->makeSlugUnique($slug);
        }

        $slugField = $this->slugOptions->slugField;

        $this->$slugField = $slug;
    }

    /**
     * Generate a non unique slug for this record.
     */
    protected function generateNonUniqueSlug(): string
    {
        if ($this->hasCustomSlugBeenUsed()) {
            $slugField = $this->slugOptions->slugField;

            return $this->$slugField;
        }

        return str_slug($this->getSlugSourceString());
    }

    /**
     * Determine if a custom slug has been saved.
     */
    protected function hasCustomSlugBeenUsed(): bool
    {
        $slugField = $this->slugOptions->slugField;

        return $this->getOriginal($slugField) != $this->$slugField;
    }

    /**
     * Get the string that should be used as base for the slug.
     */
    protected function getSlugSourceString(): string
    {
        if (is_callable($this->slugOptions->generateSlugFrom)) {
            $slugSourceString = call_user_func($this->slugOptions->generateSlugFrom, $this);
            return substr($slugSourceString, 0, $this->slugOptions->maximumLength);
        }

        $slugSourceString = collect($this->slugOptions->generateSlugFrom)
            ->map(function (string $fieldName) : string {
                return $this->$fieldName ?? '';
            })
            ->implode('-');

        return substr($slugSourceString, 0, $this->slugOptions->maximumLength);
    }

    /**
     * Make the given slug unique.
     */
    protected function makeSlugUnique(string $slug): string
    {
        $originalSlug = $slug;
        $i = 1;

        while ($this->otherRecordExistsWithSlug($slug) || $slug === '') {
            $slug = $originalSlug.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Determine if a record exists with the given slug.
     */
    protected function otherRecordExistsWithSlug(string $slug): bool
    {
        $existing = static::where($this->slugOptions->slugField, $slug)
            ->where($this->getKeyName(), '!=', $this->getKey() ?? '0');

        if ($this->usesSoftDeletes()) {
            $existing->withTrashed();
        }

        return (bool) $existing->first();
    }

    /**
     * This function will throw an exception when any of the options is missing or invalid.
     */
    protected function guardAgainstInvalidSlugOptions()
    {
        if (!count($this->slugOptions->generateSlugFrom)) {
            throw InvalidOption::missingFromField();
        }

        if (!strlen($this->slugOptions->slugField)) {
            throw InvalidOption::missingSlugField();
        }

        if ($this->slugOptions->maximumLength <= 0) {
            throw InvalidOption::invalidMaximumLength();
        }
    }

    /**
     * Determine if the model uses soft deletes.
     *
     * @return bool
     */
    protected function usesSoftDeletes(): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses($this)
        );
    }
}
