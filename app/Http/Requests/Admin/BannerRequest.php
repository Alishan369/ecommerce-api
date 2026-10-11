<?php

namespace App\Http\Requests\Admin;

use App\Models\Banner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST) and update (PUT/PATCH) a home page banner. Route is admin-gated. */
class BannerRequest extends FormRequest
{
    /** Image upload limit, in kilobytes. Uploads are compressed to WebP after validation. */
    private const MAX_UPLOAD_KB = 6144;

    /** Video upload limit, in kilobytes. */
    private const MAX_VIDEO_KB = 20480;

    public function rules(): array
    {
        $updating = $this->route('banner') instanceof Banner;
        $required = $updating ? 'sometimes' : 'required';
        $image = ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_UPLOAD_KB];

        return [
            'placement' => [$required, Rule::in(Banner::PLACEMENTS)],
            'eyebrow' => ['sometimes', 'nullable', 'string', 'max:60'],
            'title' => [$required, 'string', 'max:120'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cta_label' => ['sometimes', 'nullable', 'string', 'max:40'],
            // A path on this site (/shop?…) or a full http(s) URL — never javascript: or protocol-relative.
            'link_url' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:#^(/(?!/)|https?://)\S*$#i'],
            'image' => [$required, ...$image, 'dimensions:min_width=800'],
            'image_mobile' => ['sometimes', 'nullable', ...$image, 'dimensions:min_width=400'],
            'remove_image_mobile' => ['sometimes', 'boolean'],
            // Videos are stored as uploaded — export them small (720p, under ~2 MB) before uploading.
            'video' => ['sometimes', 'nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:'.self::MAX_VIDEO_KB],
            'remove_video' => ['sometimes', 'boolean'],
            'text_tone' => ['sometimes', Rule::in(Banner::TONES)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', ...(is_string($this->input('starts_at')) && $this->input('starts_at') !== '' ? ['after:starts_at'] : [])],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Upload a banner image.',
            'image.dimensions' => 'Use an image at least 800px wide (1920×800 works best for the slider).',
            'image_mobile.dimensions' => 'Use a phone image at least 400px wide (1080×1350 works best).',
            'image.max' => 'The image must be 6 MB or smaller.',
            'image_mobile.max' => 'The phone image must be 6 MB or smaller.',
            'video.max' => 'The video must be 20 MB or smaller — export it at 720p.',
            'video.mimetypes' => 'Upload an MP4 or WebM video.',
            'link_url.regex' => 'Use a page on this site (starting with /) or a full https:// address.',
            'ends_at.after' => 'The end date must be after the start date.',
        ];
    }
}
