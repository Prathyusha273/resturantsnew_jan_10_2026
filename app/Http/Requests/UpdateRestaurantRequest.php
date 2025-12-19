<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'restaurant_slug' => ['nullable', 'string', 'max:255'],
            'zone_slug' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:25'],
            'location' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'zone_id' => ['nullable', 'string', 'exists:zone,id'],
            'cuisine_id' => ['nullable', 'string', 'exists:vendor_cuisines,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'exists:vendor_categories,id'],
            'restaurant_cost' => ['nullable', 'numeric', 'min:0'],
            'open_dine_time' => ['nullable', 'date_format:H:i'],
            'close_dine_time' => ['nullable', 'date_format:H:i'],
            'admin_commission' => ['nullable', 'numeric', 'min:0'],
            'admin_commission_type' => ['required_with:admin_commission', 'in:Percent,Fixed'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['image', 'max:5120'],
            'remove_gallery' => ['nullable', 'array'],
            'remove_gallery.*' => ['string', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
            'story_thumbnail' => ['nullable', 'image', 'max:5120'],
            'story_video' => ['nullable', 'mimetypes:video/mp4,video/quicktime', 'max:51200'],
            'special_discount_enable' => ['nullable', 'boolean'],
            'special_discount' => ['nullable', 'array'],
            'special_discount.*' => ['array'],
            'special_discount.*.*.from' => ['nullable', 'date_format:H:i'],
            'special_discount.*.*.to' => ['nullable', 'date_format:H:i'],
            'special_discount.*.*.discount' => ['nullable', 'numeric', 'min:0'],
            'special_discount.*.*.type' => ['nullable', 'in:percentage,amount'],
            'special_discount.*.*.discount_type' => ['nullable', 'in:delivery,dinein'],
            'is_open' => ['nullable', 'boolean'],
            'enabled_dine_in_future' => ['nullable', 'boolean'],
            'working_hours' => ['nullable', 'array'],
            'working_hours.*' => ['array'],
            'working_hours.*.*.from' => ['nullable', 'date_format:H:i'],
            'working_hours.*.*.to' => ['nullable', 'date_format:H:i'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'restaurant name',
            'restaurant_slug' => 'restaurant slug',
            'zone_id' => 'zone',
            'cuisine_id' => 'cuisine',
            'category_ids' => 'categories',
        ];
    }
}

