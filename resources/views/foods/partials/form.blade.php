@php
    $editing = isset($food);
    $selectedCategory = old('categoryID', $food->categoryID ?? '');
    $existingPhoto = $food->photo ?? null;
    $placeholderImage = $placeholderImage ?? asset('assets/images/placeholder.png');
    $extraPhotos = $extraPhotos ?? [];
    $keepPhotosOld = old('keep_photos', $extraPhotos);

    $oldAddOnTitles = old('add_ons_title', []);
    $oldAddOnPrices = old('add_ons_price', []);
    $addOnRows = [];

    if (!empty($oldAddOnTitles) || !empty($oldAddOnPrices)) {
        $count = max(count($oldAddOnTitles), count($oldAddOnPrices));
        for ($i = 0; $i < $count; $i++) {
            $addOnRows[] = [
                'title' => $oldAddOnTitles[$i] ?? '',
                'price' => $oldAddOnPrices[$i] ?? '',
            ];
        }
    } elseif (!empty($addOns ?? [])) {
        $addOnRows = $addOns;
    }

    if (empty($addOnRows)) {
        $addOnRows = [['title' => '', 'price' => '']];
    }

    $oldSpecLabels = old('specification_label', []);
    $oldSpecValues = old('specification_value', []);
    $specRows = [];

    if (!empty($oldSpecLabels) || !empty($oldSpecValues)) {
        $count = max(count($oldSpecLabels), count($oldSpecValues));
        for ($i = 0; $i < $count; $i++) {
            if (($oldSpecLabels[$i] ?? '') === '' && ($oldSpecValues[$i] ?? '') === '') {
                continue;
            }
            $specRows[] = [
                'label' => $oldSpecLabels[$i] ?? '',
                'value' => $oldSpecValues[$i] ?? '',
            ];
        }
    } elseif (!empty($specifications ?? [])) {
        foreach ($specifications as $label => $value) {
            $specRows[] = ['label' => $label, 'value' => $value];
        }
    }

    $galleryTextarea = old('gallery_urls');
    if ($galleryTextarea === null && !empty($extraPhotos)) {
        $nonPrimary = array_filter($extraPhotos, function ($photo) use ($existingPhoto) {
            return $photo !== $existingPhoto;
        });
        $galleryTextarea = implode(PHP_EOL, $nonPrimary);
    }
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please fix the following issues:</strong>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Food Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $food->name ?? '') }}" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Price <span class="text-danger">*</span></label>
            <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', $food->price ?? '') }}" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Discount Price</label>
            <input type="number" step="0.01" name="disPrice" class="form-control" value="{{ old('disPrice', $food->disPrice ?? '') }}">
        </div>
    </div>
</div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="control-label font-weight-bold">
                    {{ trans('lang.food_category_id') }} <span class="text-danger">*</span>
                </label>

                <!-- Selected categories display -->
                <div id="selected_categories" class="mb-2"></div>

                <!-- Search box -->
                <input type="text"
                       id="food_category_search"
                       class="form-control mb-2"
                       placeholder="Search categories...">

                <!-- Multi-select -->
                <select id="food_category"
                        name="categoryID"
                        class="form-control"
                        multiple
                        required>
                    <option value="">Select categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ $selectedCategory === $category->id ? 'selected' : '' }}>
                            {{ $category->title }}
                        </option>
                    @endforeach
                </select>
                <small class="form-text text-muted">
                    {{ trans('lang.food_category_id_help') }}
                </small>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Quantity</label>
            <input type="number" name="quantity" class="form-control" value="{{ old('quantity', $food->quantity ?? -1) }}">
            <small class="form-text text-muted">Use -1 for unlimited</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label class="control-label font-weight-bold">Status</label>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="publish" name="publish" {{ old('publish', $food->publish ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="publish">Published</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="isAvailable" name="isAvailable" {{ old('isAvailable', $food->isAvailable ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="isAvailable">Available</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="nonveg" name="nonveg" {{ old('nonveg', $food->nonveg ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="nonveg">Non-Veg</label>
            </div>
        </div>
    </div>
</div>

<div class="form-group">
    <div class="col-md-6">
    <label class="control-label font-weight-bold">Description <span class="text-danger">*</span></label>
    <textarea name="description" rows="4" class="form-control" required>{{ old('description', $food->description ?? '') }}</textarea>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Image</label>
            <input type="file" name="photo_upload" class="form-control-file">
            <small class="form-text text-muted">Recommended size 800x600px.</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Or Photo URL</label>
            <input type="url" name="photo_url" class="form-control" value="{{ old('photo_url') }}">
            <small class="form-text text-muted">Paste a direct image URL if you host images elsewhere.</small>
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="control-label font-weight-bold d-block">Current Image</label>
    <img src="{{ $existingPhoto ?: $placeholderImage }}" alt="Current photo" class="rounded shadow" style="width: 160px; height: 120px; object-fit: cover;">
    @if ($editing && $existingPhoto)
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo">
            <label class="form-check-label" for="remove_photo">Remove photo</label>
        </div>
    @else
        <small class="form-text text-muted">Placeholder image will be used until you upload your own photo.</small>
    @endif
</div>

@if (!empty($extraPhotos))
    <div class="form-group">
        <label class="control-label font-weight-bold">Existing Gallery Photos</label>
        <div class="row">
            @foreach ($extraPhotos as $index => $photoUrl)
                <div class="col-md-3 col-6 mb-3 text-center">
                    <div class="border rounded p-2">
                        <img src="{{ $photoUrl }}" alt="Gallery Photo" class="img-fluid mb-2" style="height: 90px; object-fit: cover;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="keep_photos[]" value="{{ $photoUrl }}" id="keepPhoto{{ $index }}" {{ in_array($photoUrl, (array) $keepPhotosOld, true) ? 'checked' : '' }}>
                            <label class="form-check-label small" for="keepPhoto{{ $index }}">Keep Photo</label>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="row" style="display: none">
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Gallery Uploads</label>
            <input type="file" name="gallery_uploads[]" class="form-control-file" multiple>
            <small class="form-text text-muted">You can upload multiple images at once.</small>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label class="control-label font-weight-bold">Gallery URLs (one per line)</label>
            <textarea name="gallery_urls" rows="4" class="form-control">{{ $galleryTextarea }}</textarea>
        </div>
    </div>
</div>

<hr>
<div class="mt-4 border border 4px solid p-4">
    <div class="d-flex justify-content-between align-items-center">
        <label class="btn btn-primary mb-3">Add-ons</label>
        <button type="button" class="btn btn-sm btn-outline-primary" data-addons-add>Add new</button>
    </div>
    <small class="form-text text-muted mb-2">Define optional add-ons for this food item.</small>
    <div data-addons-container>
        @foreach ($addOnRows as $row)
            <div class="repeatable-row border rounded p-3 mb-2">
                <div class="form-row">
                    <div class="col-md-7">
                        <input type="text" name="add_ons_title[]" class="form-control" placeholder="Title" value="{{ $row['title'] }}">
                    </div>
                    <div class="col-md-4">
                        <input type="number" step="0.01" name="add_ons_price[]" class="form-control" placeholder="Price" value="{{ $row['price'] }}">
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<hr>
<div class="mt-4 border border 4px solid p-4">
    <div class="d-flex justify-content-between align-items-center">
        <label class="btn btn-primary mb-3">Product Specifications</label>
        <button type="button" class="btn btn-sm btn-outline-primary" data-specs-add>Add new</button>
    </div>
    <small class="form-text text-muted mb-2">Use specifications to highlight key product details (e.g., spicy level, calories).</small>
    <div data-specs-container>
        @forelse ($specRows as $row)
            <div class="repeatable-row border rounded p-3 mb-2">
                <div class="form-row">
                    <div class="col-md-6">
                        <input type="text" name="specification_label[]" class="form-control" placeholder="Label" value="{{ $row['label'] }}">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="specification_value[]" class="form-control" placeholder="Value" value="{{ $row['value'] }}">
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="repeatable-row border rounded p-3 mb-2">
                <div class="form-row">
                    <div class="col-md-6">
                        <input type="text" name="specification_label[]" class="form-control" placeholder="Label">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="specification_value[]" class="form-control" placeholder="Value">
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
                    </div>
                </div>
            </div>
        @endforelse
    </div>
</div>

<div class="text-center mt-4">
    <button type="submit" class="btn btn-primary px-5">
        <i class="fa fa-save mr-1"></i> {{ $editing ? 'Update Food' : 'Create Food' }}
    </button>
    <a href="{{ route('foods') }}" class="btn btn-secondary mx-2">
        <i class="fa fa-undo mr-1"></i> Cancel
    </a>
</div>

<template id="add-on-template">
    <div class="repeatable-row border rounded p-3 mb-2">
        <div class="form-row">
            <div class="col-md-7">
                <input type="text" name="add_ons_title[]" class="form-control" placeholder="Title">
            </div>
            <div class="col-md-4">
                <input type="number" step="0.01" name="add_ons_price[]" class="form-control" placeholder="Price">
            </div>
            <div class="col-md-1 d-flex align-items-center">
                <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
            </div>
        </div>
    </div>
</template>

<template id="spec-template">
    <div class="repeatable-row border rounded p-3 mb-2">
        <div class="form-row">
            <div class="col-md-6">
                <input type="text" name="specification_label[]" class="form-control" placeholder="Label">
            </div>
            <div class="col-md-5">
                <input type="text" name="specification_value[]" class="form-control" placeholder="Value">
            </div>
            <div class="col-md-1 d-flex align-items-center">
                <button type="button" class="btn btn-link text-danger" data-remove-row>&times;</button>
            </div>
        </div>
    </div>
</template>

