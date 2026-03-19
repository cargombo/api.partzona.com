<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category1688 extends Model
{
    protected $table = 'categories_1688';

    protected $fillable = [
        'category_id',
        'chinese_name',
        'translated_name',
        'parent_category_id',
        'leaf',
        'level',
        'status',
        'partner_count',
    ];

    protected $casts = [
        'category_id' => 'integer',
        'parent_category_id' => 'integer',
        'leaf' => 'boolean',
        'partner_count' => 'integer',
    ];

    /**
     * Alt kateqoriyalar
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_category_id', 'category_id');
    }

    /**
     * Ana kateqoriya
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_category_id', 'category_id');
    }

    /**
     * Bu kateqoriyaya icazəsi olan partnerlər
     */
    public function partners()
    {
        return $this->belongsToMany(
            Partner::class,
            'partner_categories',
            'category_id',
            'partner_id',
            'category_id',
            'id'
        );
    }

    /**
     * Root kateqoriyalar (parent_category_id = 0)
     */
    public function scopeRoots($query)
    {
        return $query->where('parent_category_id', 0);
    }
}
