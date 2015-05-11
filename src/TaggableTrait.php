<?php namespace Conner\Tagging;

use Illuminate\Support\Facades\Config;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Copyright (C) 2014 Robert Conner
 */
trait TaggableTrait {

	/**
	 * Boot the soft taggable trait for a model.
	 *
	 * @return void
	 */
	public static function bootTaggableTrait()
    {
        // If Untagging on Delete
		if(static::untagOnDelete()) {
			static::deleting(function($model) {
				$model->untag();
			});
		}
	}
	
	/**
	 * Return collection of tags related to the tagged model
	 *
	 * @return Illuminate\Database\Eloquent\Collection
	 */
	public function tagged()
	{
		return $this->morphMany('Conner\Tagging\Tagged', 'taggable');
	}

    /**
     * Perform the action of tagging the model with the given string
     *
     * @param string|array  $tagNames
     * @param string        $tagDept
     */
	public function tag($tagNames, $tagDept = 'support')
	{
		$tagNames = TaggingUtil::makeTagArray($tagNames);
		
		foreach($tagNames as $tagName) {
			$this->addTag($tagName, $tagDept);
		}
	}
	
	/**
	 * Return array of the tag names related to the current model
	 *
	 * @return array
	 */
	public function tagNames()
	{
		$tagNames = array();
		$tagged = $this->tagged()->get(array('tag_name'));

		foreach($tagged as $tagged) {
			$tagNames[] = $tagged->tag_name;
		}
		
		return $tagNames;
	}

	/**
	 * Return array of the tag slugs related to the current model
	 *
	 * @return array
	 */
	public function tagSlugs()
	{
		$tagSlugs = array();
		$tagged = $this->tagged()->get(array('tag_slug'));

		foreach($tagged as $tagged) {
			$tagSlugs[] = $tagged->tag_slug;
		}
		
		return $tagSlugs;
	}

    /**
     * Remove the tag from this model
     *
     * @param null|string|array $tagNames
     * @param string $tagDept
     */
	public function untag($tagNames = null, $tagDept = 'support')
	{
		if(is_null($tagNames)) {
			$currentTagNames = $this->tagNames();

			foreach($currentTagNames as $tagName) {
				$this->removeTag($tagName, $tagDept);
			}
			return;
		}
		
		$tagNames = TaggingUtil::makeTagArray($tagNames);
		
		foreach($tagNames as $tagName) {
			$this->removeTag($tagName, $tagDept);
		}
	}

    /**
     * Replace the tags from this model
     *
     * @param $tagNames
     * @param $tagDept
     */
	public function retag($tagNames, $tagDept)
	{
		$tagNames = TaggingUtil::makeTagArray($tagNames);
		$currentTagNames = $this->tagNames();
		
		$deletions = array_diff($currentTagNames, $tagNames);
		$additions = array_diff($tagNames, $currentTagNames);
		
		foreach($deletions as $tagName) {
			$this->removeTag($tagName, $tagDept);
		}
		foreach($additions as $tagName) {
			$this->addTag($tagName, $tagDept);
		}
	}
	
	/**
	 * Filter model to subset with the given tags
	 *
	 * @param $tagNames array|string
	 */
	public function scopeWithAllTags($query, $tagNames)
	{
		$tagNames = TaggingUtil::makeTagArray($tagNames);
		
		$normalizer = config('tagging.normalizer');
		$normalizer = empty($normalizer) ? 'Conner\Tagging\TaggingUtil::slug' : $normalizer;

		foreach($tagNames as $tagSlug) {
			$query->whereHas('tagged', function($q) use($tagSlug, $normalizer) {
				$q->where('tag_slug', '=', call_user_func($normalizer, $tagSlug));
			});
		}
		
		return $query;
	}
		
	/**
	 * Filter model to subset with the given tags
	 *
	 * @param $tagNames array|string
	 */
	public function scopeWithAnyTag($query, $tagNames)
	{
		$tagNames = TaggingUtil::makeTagArray($tagNames);

        // Normalize
		$normalizer = config('tagging.normalizer');
		$normalizer = empty($normalizer)
            ? '\Conner\Tagging\TaggingUtil::slug'
            : $normalizer;
		
		$tagNames = array_map($normalizer, $tagNames);

		return $query->whereHas('tagged', function($q) use($tagNames) {
			$q->whereIn('tag_slug', $tagNames);
		});
	}
	
	/**
	 * Adds a single tag
	 *
     * @param $tagName string
     * @param $tagDept string
     * @throws ForbiddenTagCreation
	 */
	private function addTag($tagName, $tagDept = 'support')
	{
        // Grab User
        $user = JWTAuth::parseToken()->toUser();

        $tagName = trim($tagName);
        $tagDept = trim(strtolower($tagDept));

        // Find out if already exists.
        $tagAlreadyExists = TaggingUtil::tagExists($tagName, $tagDept);

        // (Support Only) If Not Exists But Is Training Super or If Already Exists
        if ( ! $tagDept == 'support' || ((! $tagAlreadyExists && $user->hasRole('Training Supervisor')) || $tagAlreadyExists)) {

            // Normalize Name
            $normalizer = config('tagging.normalizer');
            $normalizer = empty($normalizer)
                ? '\Conner\Tagging\TaggingUtil::slug'
                : $normalizer;

            $tagSlug = call_user_func($normalizer, $tagName);

            $previousCount = $this->tagged()
                ->where('tag_slug', '=', $tagSlug)->take(1)->count();

            if($previousCount >= 1) { return; }

            // Create Pretty Version
            $displayer = config('tagging.displayer');
            $displayer = empty($displayer)
                ? '\Illuminate\Support\Str::title'
                : $displayer;

            $tagged = new Tagged(array(
                'tag_name' => call_user_func($displayer, $tagName),
                'tag_slug' => $tagSlug,
            ));

            $this->tagged()->save($tagged);

            // Increment Count & Save If Not Exists
            TaggingUtil::incrementCount($tagName, $tagDept, $tagSlug, 1);

        } else {
            // Else Not Allowed, Probably Should Tell User
            throw new ForbiddenTagCreation;
        }
	}

    /**
     * Removes a single tag
     *
     * @param $tagName string
     * @param string $tagDept
     */
	private function removeTag($tagName, $tagDept = 'support')
	{
		$tagName = trim($tagName);
        $tagDept = strtolower(trim($tagDept));
		
		$normalizer = config('tagging.normalizer');
        $normalizer = empty($normalizer) 
            ? '\Conner\Tagging\TaggingUtil::slug' 
            : $normalizer;
		
		$tagSlug = call_user_func($normalizer, $tagName);

        // If can find tag - decrement count
		if($count = $this->tagged()->where('tag_slug', '=', $tagSlug)->delete()) {
			TaggingUtil::decrementCount($tagName, $tagDept, $tagSlug, $count);
		}
	}

	/**
	 * Return an array of all of the tags that are in use by this model
	 *
	 * @return Collection
	 */
	public static function existingTags()
	{
		return Tagged::distinct()
			->join('tagging_tags', 'tag_slug', '=', 'tagging_tags.slug')
			->where('taggable_type', '=', (new static)->getMorphClass())
			->orderBy('tag_slug', 'ASC')
			->get(array('tag_slug as slug', 'tag_name as name', 'tagging_tags.count as count'));
	}
	
	/**
	 * Should untag on delete
	 */
	public static function untagOnDelete()
	{
		return isset(static::$untagOnDelete)
			? static::$untagOnDelete
			: Config::get('tagging.untag_on_delete');
	}

}
