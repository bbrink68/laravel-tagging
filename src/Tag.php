<?php namespace Conner\Tagging;

use Conner\Tagging\TaggingUtil;
use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * Copyright (C) 2014 Robert Conner
 */
class Tag extends Eloquent {
    
    /**
     * Table Name
     */
    protected $table = 'tagging_tags';

    /**
     * Timestamps
     */
    public $timestamps = false;

    /**
     * Soft Deletes
     */
    protected $softDelete = false;

    /**
     * Fillable Columns
     */
    public $fillable = [
        'name',
        'department'
    ];

    /**
     * Constructor
     * @param array $attributes
     */
	public function __construct(array $attributes = array()) {
		parent::__construct($attributes);
		
		if($connection = config('tagging.connection')) {
			$this->connection = $connection;
		}
	}

    /**
     * Save New Tag
     */
	public function save(array $options = array()) {
        
        // Create New Validator
        $validator = \Validator::make(
			array('name' => $this->name),
			array('name' => 'required|min:1')
		);

        // Good Validation
        if($validator->passes()) {
            // Get Normalizer & Normalize
			$normalizer = config('tagging.normalizer');
            $normalizer = empty($normalizer) 
                ? '\Conner\Tagging\TaggingUtil::slug' 
                : $normalizer;
			
			$this->slug = call_user_func($normalizer, $this->name);
            // Save New Tag
            parent::save($options);
		} else {
			throw new \Exception('Tag Name is required');
		}
	}
	
	/**
	 * Get suggested tags
	 */
	public function scopeSuggested($query) {
		return $query->where('suggest', true);
	}
	
	/**
	 * Name auto-mutator
	 */
    public function setNameAttribute($value) {
        // Get Displayer from Config
		$displayer = config('tagging.displayer');
        $displayer = empty($displayer) 
            ? '\Illuminate\Support\Str::title' 
            : $displayer;

        // Set Name to Prettier Version
		$this->attributes['name'] = call_user_func($displayer, $value);
	}
	
}
