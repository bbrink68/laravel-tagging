<?php namespace Conner\Tagging;

use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * Copyright (C) 2014 Robert Conner
 */
class Tagged extends Eloquent {

    /**
     * Table Name
     * @var string
     */
	protected $table = 'tagging_tagged';

    /**
     * Timestamps
     * @var bool
     */
	public $timestamps = false;

    /**
     * Fillable Columns
     * @var array
     */
	protected $fillable = ['tag_name', 'tag_slug'];

    /**
     * Taggable Relationship
     * @return mixed
     */
	public function taggable() {
		return $this->morphTo();
	}

}