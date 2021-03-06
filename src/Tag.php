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
			array('name' => $this->name, 'department' => $this->department),
            array('name' => 'required|min:1', 'department' => 'required')
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
			throw new \Exception('Tag Name & Department Are Required');
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

    /**
     * Department auto-mutator
     */
    public function setDepartmentAttribute($value) {
        // To Lowercase
        $value = strtolower($value);

        // Make Sure A Valid Department is being used
        // else, default to support 
        if (!in_array($value, relevant_depts())) {
            $value = 'support';
        }

        $this->attributes['department'] = $value;
    }
}
