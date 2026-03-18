@extends('layouts.app')

@section('content')
    <div class="page-wrapper">
        <div class="row page-titles">
            <div class="col-md-6 align-self-center">
                <h3 class="text-themecolor">Add Foods from Master Products</h3>
            </div>
            <div class="col-md-6 align-self-center text-right">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('foods') }}">Foods</a></li>
                    <li class="breadcrumb-item active">Create</li>
                </ol>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Back to Home Button -->
            <div class="mb-3">
                <a href="{{ route('foods') }}" class="btn btn-outline-primary">
                    <i class="fa fa-arrow-left mr-1"></i> Back to Foods List
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <!-- Categories Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="fa fa-folder-open mr-2"></i>Select Category
                    </h4>
                    <small class="text-muted">Search and select a category to view available products</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label font-weight-bold">
                                    <i class="fa fa-search mr-1"></i>Search Categories <span class="text-danger">*</span>
                                </label>

                                <!-- Combined Search and Dropdown (Google-like Autocomplete) -->
                                <div class="category-autocomplete-wrapper" style="position: relative;">
                                    <input type="text"
                                           id="category-autocomplete"
                                           class="form-control"
                                           placeholder="Search and select a category..."
                                           autocomplete="off">
                                    <input type="hidden" id="category-id-hidden" value="">

                                    <!-- Autocomplete dropdown -->
                                    <div id="category-autocomplete-dropdown"
                                         class="category-autocomplete-dropdown"
                                         style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ced4da; border-top: none; border-radius: 0 0 0.25rem 0.25rem; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                        <!-- Options will be populated by JavaScript -->
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    Search and select a category to view products from master catalog
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Section (Hidden initially) -->
            <div class="card" id="products-section" style="display: none;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">
                            <i class="fa fa-shopping-bag mr-2"></i>Products in Category: <span id="selected-category-name"></span>
                        </h4>
                        <small class="text-muted">Select products to add to your menu</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="back-to-categories">
                        <i class="fa fa-arrow-left mr-1"></i> Back to Categories
                    </button>
                </div>
                <div class="card-body">
                    <!-- Search and Pagination Controls -->
                    <div class="row mb-3" id="products-controls" style="display: none;">
                        <div class="col-md-6">
                            <div class="form-group mb-0">
                                <label class="mr-2">Show</label>
                                <select id="per-page-select" class="form-control d-inline-block" style="width: auto;">
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <label class="ml-2">entries</label>
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="form-group mb-0 d-inline-block">
                                <label class="mr-2" style="display: inline-block;">Search:</label>
                                <input type="text"
                                       id="product-search-input"
                                       class="form-control"
                                       placeholder="Search products..."
                                       autocomplete="off"
                                       style="width: 200px; display: inline-block;">
                            </div>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="products-loading" class="text-center py-5" style="display: none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading products...</p>
                    </div>

                    <!-- Products Container -->
                    <div class="table-responsive" id="products-table-wrapper" style="display: none;">
                        <table class="table table-hover table-bordered" id="products-table">
                            <thead class="thead-light">
                            <tr>
                                <th style="width: 60px; min-width: 60px;" class="text-center">
                                    <label for="select-all-products" style="cursor: pointer; display: block; margin-bottom: 0; padding: 5px;">
                                        <input type="checkbox"
                                               id="select-all-products"
                                               style="position: relative !important; left: auto !important; top: auto !important; width: 20px !important; height: 20px !important; cursor: pointer !important; opacity: 1 !important; visibility: visible !important; display: block !important; margin: 0 auto !important; -webkit-appearance: checkbox !important; -moz-appearance: checkbox !important; appearance: checkbox !important; z-index: 10 !important;">
                                        <small style="display: block; font-size: 0.75rem; margin-top: 4px; font-weight: 600;">Select</small>
                                    </label>
                                </th>
                                <th style="width: 100px;">Image</th>
                                <th>Product Name</th>
                                <th>Description</th>
                                <th style="width: 120px;">Merchant Price *</th>
                                <th style="width: 120px;">Online Price *</th>
                                <th style="width: 200px;">Calculation</th>
                                <th style="width: 120px;">Discount Price</th>
                                <th style="width: 200px;">Add-ons</th>
                                <th style="width: 100px;">Options</th>
                                <th style="width: 100px;">Published</th>
                                <th style="width: 100px;">Available</th>
                                <th style="width: 150px;">Available Days</th>
                                <th style="width: 200px;">Available Timings</th>
                            </tr>
                            </thead>
                            <tbody id="products-container">
                            <!-- Products will be loaded here via AJAX -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="products-pagination" class="mt-3" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div id="pagination-info" class="mt-2">
                                    <small class="text-muted">Showing 0 to 0 of 0 entries</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <nav aria-label="Products pagination" class="float-right">
                                    <ul class="pagination mb-0" id="pagination-links">
                                        <!-- Pagination links will be generated here -->
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>

                    <!-- No Products Message -->
                    <div id="no-products-message" class="text-center py-5" style="display: none;">
                        <i class="fa fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No products available in this category</p>
                    </div>

                    <!-- Selected Products Summary - Fixed at Bottom -->
                    <div id="selected-products-summary" class="mt-4 p-4 bg-light rounded border" style="display: none; position: sticky; bottom: 0; z-index: 10;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">
                                    <i class="fa fa-check-circle mr-2 text-success"></i>
                                    Selected Products: <span id="selected-count" class="badge badge-primary" style="font-size: 1rem;">0</span>
                                </h5>
                                <small class="text-muted">Only selected products will be added to your menu</small>
                            </div>
                            <button type="button" class="btn btn-primary btn-lg" id="save-selected-products">
                                <i class="fa fa-save mr-2"></i> Save <span id="save-count">0</span> Product(s)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Selection Form (Hidden) -->
    <form id="bulk-products-form" method="POST" action="{{ route('foods.store') }}" style="display: none;">
        @csrf
        <div id="selected-products-inputs"></div>
    </form>

@endsection

@section('scripts')
    <script>
        // Subscription info from backend - available globally
        const hasSubscription = {{ $hasSubscription ? 'true' : 'false' }};
        const applyPercentage = {{ $applyPercentage }};
        const planType = '{{ $planType ?? 'commission' }}';
        const gstAgreed = {{ $gstAgreed ? 'true' : 'false' }};
        const gstPercentage = 5; // 5% GST

        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            let selectedProducts = {}; // Store selected products with their custom data
            let currentCategoryId = null;
            let currentCategoryName = null;
            let currentPage = 1;
            let currentPerPage = 10;
            let currentSearch = '';
            let searchTimeout = null;
            let paginationData = null;
            let selectionOrder = []; // Maintain selection order for index page
            let currentProductsData = {}; // Store product data by ID

            // Category autocomplete functionality (Google-like)
            const categoryAutocomplete = document.getElementById('category-autocomplete');
            const categoryAutocompleteDropdown = document.getElementById('category-autocomplete-dropdown');
            const categoryIdHidden = document.getElementById('category-id-hidden');

            // Store all categories from the original select dropdown data
            const allCategories = [
                    @foreach ($categories as $category)
                {
                    id: '{{ $category->id }}',
                    title: '{{ addslashes($category->title) }}',
                    photo: '{{ $category->photo ?? '' }}',
                    searchText: '{{ addslashes(mb_strtolower($category->title)) }}'
                }{{ !$loop->last ? ',' : '' }}
                    @endforeach
            ];

            let filteredCategories = [];
            let selectedIndex = -1;
            let isDropdownOpen = false;

            // Filter categories based on search term
            function filterCategories(searchTerm) {
                const term = searchTerm.toLowerCase().trim();
                if (term === '') {
                    return allCategories;
                }
                return allCategories.filter(category =>
                    category.searchText.includes(term)
                );
            }

            // Render dropdown options
            function renderDropdown(categories) {
                categoryAutocompleteDropdown.innerHTML = '';

                if (categories.length === 0) {
                    const noResults = document.createElement('div');
                    noResults.className = 'category-autocomplete-item';
                    noResults.style.padding = '10px';
                    noResults.style.color = '#6c757d';
                    noResults.textContent = 'No categories found';
                    categoryAutocompleteDropdown.appendChild(noResults);
                    return;
                }

                categories.forEach((category, index) => {
                    const item = document.createElement('div');
                    item.className = 'category-autocomplete-item';
                    item.style.padding = '10px 15px';
                    item.style.cursor = 'pointer';
                    item.style.borderBottom = index < categories.length - 1 ? '1px solid #e9ecef' : 'none';
                    item.textContent = category.title;
                    item.dataset.categoryId = category.id;
                    item.dataset.categoryTitle = category.title;
                    item.dataset.categoryPhoto = category.photo;

                    // Hover effect
                    item.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#f8f9fa';
                        selectedIndex = index;
                        updateSelectedItem();
                    });

                    item.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = '';
                    });

                    // Click to select
                    item.addEventListener('click', function() {
                        selectCategory(category.id, category.title, category.photo);
                    });

                    categoryAutocompleteDropdown.appendChild(item);
                });
            }

            // Update selected item highlight
            function updateSelectedItem() {
                const items = categoryAutocompleteDropdown.querySelectorAll('.category-autocomplete-item:not([style*="color: #6c757d"])');
                items.forEach((item, index) => {
                    if (index === selectedIndex) {
                        item.style.backgroundColor = '#f8f9fa';
                    } else {
                        item.style.backgroundColor = '';
                    }
                });
            }

            // Select a category
            function selectCategory(categoryId, categoryTitle, categoryPhoto) {
                categoryAutocomplete.value = categoryTitle;
                categoryIdHidden.value = categoryId;
                categoryAutocompleteDropdown.style.display = 'none';
                isDropdownOpen = false;
                selectedIndex = -1;

                // Trigger category selection (existing functionality)
                triggerCategorySelection(categoryId, categoryTitle);
            }

            // Input event - filter and show dropdown
            categoryAutocomplete.addEventListener('input', function() {
                const searchTerm = this.value;
                filteredCategories = filterCategories(searchTerm);
                renderDropdown(filteredCategories);

                if (filteredCategories.length > 0 && searchTerm.length > 0) {
                    categoryAutocompleteDropdown.style.display = 'block';
                    isDropdownOpen = true;
                    selectedIndex = -1;
                } else if (searchTerm.length === 0) {
                    // Show all categories when input is empty
                    filteredCategories = allCategories;
                    renderDropdown(filteredCategories);
                    categoryAutocompleteDropdown.style.display = 'block';
                    isDropdownOpen = true;
                    selectedIndex = -1;
                } else {
                    categoryAutocompleteDropdown.style.display = 'block';
                    isDropdownOpen = true;
                    selectedIndex = -1;
                }
            });

            // Focus event - show all categories
            categoryAutocomplete.addEventListener('focus', function() {
                if (this.value.trim() === '') {
                    filteredCategories = allCategories;
                    renderDropdown(filteredCategories);
                    categoryAutocompleteDropdown.style.display = 'block';
                    isDropdownOpen = true;
                    selectedIndex = -1;
                } else {
                    filteredCategories = filterCategories(this.value);
                    renderDropdown(filteredCategories);
                    categoryAutocompleteDropdown.style.display = 'block';
                    isDropdownOpen = true;
                }
            });

            // Keyboard navigation
            categoryAutocomplete.addEventListener('keydown', function(e) {
                if (!isDropdownOpen) return;

                const items = categoryAutocompleteDropdown.querySelectorAll('.category-autocomplete-item:not([style*="color: #6c757d"])');
                if (items.length === 0) return;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        selectedIndex = (selectedIndex + 1) % items.length;
                        updateSelectedItem();
                        items[selectedIndex].scrollIntoView({ block: 'nearest' });
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
                        updateSelectedItem();
                        items[selectedIndex].scrollIntoView({ block: 'nearest' });
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (selectedIndex >= 0 && items[selectedIndex]) {
                            const categoryId = items[selectedIndex].dataset.categoryId;
                            const categoryTitle = items[selectedIndex].dataset.categoryTitle;
                            const categoryPhoto = items[selectedIndex].dataset.categoryPhoto || '';
                            selectCategory(categoryId, categoryTitle, categoryPhoto);
                        }
                        break;
                    case 'Escape':
                        e.preventDefault();
                        categoryAutocompleteDropdown.style.display = 'none';
                        isDropdownOpen = false;
                        selectedIndex = -1;
                        break;
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!categoryAutocomplete.contains(e.target) &&
                    !categoryAutocompleteDropdown.contains(e.target)) {
                    categoryAutocompleteDropdown.style.display = 'none';
                    isDropdownOpen = false;
                    selectedIndex = -1;
                }
            });

            // Trigger category selection (updated function)
            function triggerCategorySelection(categoryId = null, categoryName = null) {
                const id = categoryId || categoryIdHidden.value;
                const name = categoryName || categoryAutocomplete.value;

                if (id && name) {
                    loadProductsForCategory(id, name, 1, 10, '');
                } else {
                    // Hide products section if no category selected
                    document.getElementById('products-section').style.display = 'none';
                }
            }

            // Back to categories button
            document.getElementById('back-to-categories').addEventListener('click', function() {
                document.getElementById('products-section').style.display = 'none';
                // Scroll to category section
                document.querySelector('.card.mb-4').scrollIntoView({ behavior: 'smooth' });
                selectedProducts = {};
                updateSelectedProductsSummary();
                // Reset autocomplete
                categoryAutocomplete.value = '';
                categoryIdHidden.value = '';
                categoryAutocompleteDropdown.style.display = 'none';
                isDropdownOpen = false;
                selectedIndex = -1;
            });

            // Load products for selected category
            function loadProductsForCategory(categoryId, categoryName, page = 1, perPage = 10, search = '') {
                currentCategoryId = categoryId;
                currentCategoryName = categoryName;
                currentPage = page;
                currentPerPage = perPage;
                currentSearch = search;

                // Show products section
                document.getElementById('products-section').style.display = 'block';
                document.getElementById('selected-category-name').textContent = categoryName;

                // Show controls
                document.getElementById('products-controls').style.display = 'block';

                // Show loading
                document.getElementById('products-loading').style.display = 'block';
                document.getElementById('products-table-wrapper').style.display = 'none';
                document.getElementById('products-pagination').style.display = 'none';
                document.getElementById('products-container').innerHTML = '';
                document.getElementById('no-products-message').style.display = 'none';

                // Build query string
                const params = new URLSearchParams();
                params.append('category_id', categoryId);
                params.append('page', page.toString());
                params.append('per_page', perPage.toString());

                // Add search parameter if provided
                if (search && search.trim() !== '') {
                    params.append('search', search.trim());
                }

                // Fetch products via AJAX
                const fetchUrl = `{{ route('foods.master-products') }}?${params.toString()}`;
                fetch(fetchUrl, {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('products-loading').style.display = 'none';

                        if (data.success && data.products.length > 0) {
                            document.getElementById('products-table-wrapper').style.display = 'block';
                            paginationData = data.pagination;
                            renderProducts(data.products);
                            renderPagination(data.pagination);
                        } else {
                            document.getElementById('products-table-wrapper').style.display = 'none';
                            document.getElementById('products-pagination').style.display = 'none';
                            if (data.pagination && data.pagination.total === 0) {
                                document.getElementById('no-products-message').style.display = 'block';
                            } else {
                                document.getElementById('no-products-message').style.display = 'block';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading products:', error);
                        document.getElementById('products-loading').style.display = 'none';
                        document.getElementById('products-pagination').style.display = 'none';
                        document.getElementById('no-products-message').style.display = 'block';
                        alert('Error loading products. Please try again.');
                    });
            }

            // Render pagination
            function renderPagination(pagination) {
                const paginationContainer = document.getElementById('pagination-links');
                const paginationInfo = document.getElementById('pagination-info');
                const paginationWrapper = document.getElementById('products-pagination');

                if (!pagination || pagination.total === 0) {
                    paginationWrapper.style.display = 'none';
                    return;
                }

                paginationWrapper.style.display = 'block';
                paginationContainer.innerHTML = '';

                // Previous button
                const prevLi = document.createElement('li');
                prevLi.className = `page-item ${pagination.current_page === 1 ? 'disabled' : ''}`;
                prevLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); if(${pagination.current_page} > 1) loadProductsForCategory('${currentCategoryId}', '${currentCategoryName}', ${pagination.current_page - 1}, ${currentPerPage}, '${currentSearch.replace(/'/g, "\\'")}');">Previous</a>`;
                paginationContainer.appendChild(prevLi);

                // Page numbers
                const startPage = Math.max(1, pagination.current_page - 2);
                const endPage = Math.min(pagination.last_page, pagination.current_page + 2);

                if (startPage > 1) {
                    const firstLi = document.createElement('li');
                    firstLi.className = 'page-item';
                    firstLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); loadProductsForCategory('${currentCategoryId}', '${currentCategoryName}', 1, ${currentPerPage}, '${currentSearch.replace(/'/g, "\\'")}');">1</a>`;
                    paginationContainer.appendChild(firstLi);
                    if (startPage > 2) {
                        const ellipsis = document.createElement('li');
                        ellipsis.className = 'page-item disabled';
                        ellipsis.innerHTML = '<span class="page-link">...</span>';
                        paginationContainer.appendChild(ellipsis);
                    }
                }

                for (let i = startPage; i <= endPage; i++) {
                    const li = document.createElement('li');
                    li.className = `page-item ${i === pagination.current_page ? 'active' : ''}`;
                    li.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); loadProductsForCategory('${currentCategoryId}', '${currentCategoryName}', ${i}, ${currentPerPage}, '${currentSearch.replace(/'/g, "\\'")}');">${i}</a>`;
                    paginationContainer.appendChild(li);
                }

                if (endPage < pagination.last_page) {
                    if (endPage < pagination.last_page - 1) {
                        const ellipsis = document.createElement('li');
                        ellipsis.className = 'page-item disabled';
                        ellipsis.innerHTML = '<span class="page-link">...</span>';
                        paginationContainer.appendChild(ellipsis);
                    }
                    const lastLi = document.createElement('li');
                    lastLi.className = 'page-item';
                    lastLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); loadProductsForCategory('${currentCategoryId}', '${currentCategoryName}', ${pagination.last_page}, ${currentPerPage}, '${currentSearch.replace(/'/g, "\\'")}');">${pagination.last_page}</a>`;
                    paginationContainer.appendChild(lastLi);
                }

                // Next button
                const nextLi = document.createElement('li');
                nextLi.className = `page-item ${pagination.current_page === pagination.last_page ? 'disabled' : ''}`;
                nextLi.innerHTML = `<a class="page-link" href="#" onclick="event.preventDefault(); if(${pagination.current_page} < ${pagination.last_page}) loadProductsForCategory('${currentCategoryId}', '${currentCategoryName}', ${pagination.current_page + 1}, ${currentPerPage}, '${currentSearch.replace(/'/g, "\\'")}');">Next</a>`;
                paginationContainer.appendChild(nextLi);

                // Update info
                paginationInfo.innerHTML = `<small class="text-muted">Showing ${pagination.from} to ${pagination.to} of ${pagination.total} entries</small>`;
            }

            // Search functionality
            const productSearchInput = document.getElementById('product-search-input');
            if (productSearchInput) {
                productSearchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    const searchTerm = this.value.trim();
                    searchTimeout = setTimeout(() => {
                        if (currentCategoryId) {
                            loadProductsForCategory(currentCategoryId, currentCategoryName, 1, currentPerPage, searchTerm);
                        }
                    }, 500); // Debounce 500ms
                });
            }

            // Per page change
            const perPageSelect = document.getElementById('per-page-select');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', function() {
                    const perPage = parseInt(this.value);
                    if (currentCategoryId) {
                        loadProductsForCategory(currentCategoryId, currentCategoryName, 1, perPage, currentSearch);
                    }
                });
            }

            // Render products in the container (table row format)
            function renderProducts(products) {
                const container = document.getElementById('products-container');
                container.innerHTML = '';

                // Store product data globally for access in toggleProductSelection
                currentProductsData = {};
                products.forEach(product => {
                    currentProductsData[product.id] = product;
                });

                products.forEach(product => {
                    const productId = product.id;
                    const isSelected = selectedProducts.hasOwnProperty(productId);
                    const isExisting = product.is_existing || false;




                    // Determine row class: selected (blue) overrides existing (green)
                    let rowClass = 'product-row';
                    if (isSelected) {
                        rowClass += ' table-primary';
                    } else if (isExisting) {
                        rowClass += ' table-success';
                    }

                    const row = document.createElement('tr');
                    row.className = rowClass;
                    row.setAttribute('data-product-id', productId);
                    if (isExisting) {
                        row.setAttribute('data-is-existing', 'true');
                    }
                    if (isExisting && product.vendor_product_id) {
                        row.setAttribute('data-vendor-product-id', product.vendor_product_id);
                    }


                    row.innerHTML = `
              <td class="text-center" style="vertical-align: middle; padding: 10px !important; min-width: 60px; width: 60px;">
    <input type="checkbox"
    class="form-check-input product-checkbox"
    data-product-id="${productId}"

    ${isSelected ? 'checked' : ''}
     onchange="toggleProductSelection(${productId})"

        style="position: relative !important;
               width: 20px !important;
               height: 20px !important;
               cursor: pointer !important;
               margin: 0 auto !important;
               display: block !important;">
    ${isExisting ? '<small class="d-block mt-1 text-success" style="font-size:0.7rem;font-weight:600;">✓ Added</small>' : ''}
</td>


                <td>
                    <img src="${product.photo || '{{ $placeholderImage }}'}"
                         alt="${product.name}"
                         class="img-fluid rounded"
                         style="width: 80px; height: 80px; object-fit: cover;">
                </td>
                <td>
                    <strong>${product.name}</strong>
                </td>
                <td>
                    <small class="text-muted">${product.description || 'No description available'}</small>
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm product-merchant-price"
                           data-product-id="${productId}"
                           value="${isSelected ? (selectedProducts[productId].merchant_price || '') : (isExisting && product.vendor_merchantPrice ? product.vendor_merchantPrice : '')}"
                           placeholder="Enter merchant price"
                           onchange="updateProductData(${productId}, 'merchant_price', this.value)"
                           oninput="updateProductData(${productId}, 'merchant_price', this.value)"
                           style="min-width: 100px;">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm product-online-price"
                           data-product-id="${productId}"
                           value="${(() => {
                        // If product is selected and has online_price, use it
                        if (isSelected && selectedProducts[productId] && selectedProducts[productId].online_price) {
                            return selectedProducts[productId].online_price;
                        }
                        // If product exists and has stored vendor_price, use it (preserves manual edits)
                        if (isExisting && product.vendor_price && parseFloat(product.vendor_price) > 0) {
                            return parseFloat(product.vendor_price).toFixed(2);
                        }
                        // Otherwise, calculate from merchant price only if no stored value
                        const merchantPrice = isSelected && selectedProducts[productId] && selectedProducts[productId].merchant_price ? parseFloat(selectedProducts[productId].merchant_price) : (isExisting && product.vendor_merchantPrice ? parseFloat(product.vendor_merchantPrice) : 0);
                        if (merchantPrice > 0) {
                            const hasSub = typeof hasSubscription !== 'undefined' ? hasSubscription : false;
                            const applyPercent = typeof applyPercentage !== 'undefined' ? applyPercentage : 30;
                            const planTypeValue = typeof planType !== 'undefined' ? planType : 'commission';
                            const gstAgreedValue = typeof gstAgreed !== 'undefined' ? gstAgreed : false;
                            const gstPercent = 5;

                            const isCommissionBased = !hasSub || planTypeValue === 'commission';
                            let onlinePrice = 0;

                            if (isCommissionBased) {
                                const commission = merchantPrice * (applyPercent / 100);
                                const priceBeforeGst = merchantPrice + commission;
                                if (gstAgreedValue) {
                                    onlinePrice = priceBeforeGst;
                                } else {
                                    // GST is 5% of Merchant Price (not of price before GST)
                                    const gstAmount = merchantPrice * (gstPercent / 100);
                                    onlinePrice = priceBeforeGst + gstAmount;
                                }
                            } else {
                                if (gstAgreedValue) {
                                    onlinePrice = merchantPrice;
                                } else {
                                    onlinePrice = merchantPrice + (merchantPrice * (gstPercent / 100));
                                }
                            }
                            return onlinePrice.toFixed(2);
                        }
                        // No merchant price, return empty
                        return '';
                    })()}"
                           placeholder="0.00"
                           onchange="updateProductData(${productId}, 'online_price', this.value)"
                           oninput="updateProductData(${productId}, 'online_price', this.value)"
                           style="min-width: 100px;"
                           title="Auto-calculated from merchant price. You can edit this value manually.">
                </td>
                <td class="price-calculation-cell" style="font-size: 0.75rem; max-width: 200px;">
                    <div class="price-calculation" data-product-id="${productId}" style="white-space: normal; line-height: 1.4;">
                        ${(() => {
                        const merchantPrice = isSelected && selectedProducts[productId] && selectedProducts[productId].merchant_price ? parseFloat(selectedProducts[productId].merchant_price) : (isExisting && product.vendor_merchantPrice ? parseFloat(product.vendor_merchantPrice) : 0);
                        if (merchantPrice > 0) {
                            const hasSub = typeof hasSubscription !== 'undefined' ? hasSubscription : false;
                            const applyPercent = typeof applyPercentage !== 'undefined' ? applyPercentage : 30;
                            const planTypeValue = typeof planType !== 'undefined' ? planType : 'commission';
                            const gstAgreedValue = typeof gstAgreed !== 'undefined' ? gstAgreed : false;
                            const gstPercent = 5;

                            const isCommissionBased = !hasSub || planTypeValue === 'commission';
                            let calcText = '<strong>Calc:</strong><br>';
                            calcText += 'M: ₹' + merchantPrice.toFixed(2) + '<br>';

                            if (isCommissionBased) {
                                const commission = merchantPrice * (applyPercent / 100);
                                const priceBeforeGst = merchantPrice + commission;
                                calcText += 'Type: Comm (' + applyPercent + '%)<br>';
                                calcText += 'Comm: ₹' + commission.toFixed(2) + '<br>';
                                calcText += 'B4 GST: ₹' + priceBeforeGst.toFixed(2) + '<br>';

                                if (gstAgreedValue) {
                                    calcText += '<span class="text-success">GST: Absorbed</span><br>';
                                    calcText += '<strong>Online: ₹' + priceBeforeGst.toFixed(2) + '</strong>';
                                } else {
                                    // GST is 5% of Merchant Price (not of price before GST)
                                    const gstAmount = merchantPrice * (gstPercent / 100);
                                    const finalPrice = priceBeforeGst + gstAmount;
                                    calcText += '<span class="text-warning">GST: +₹' + gstAmount.toFixed(2) + '</span><br>';
                                    calcText += '<strong>Online: ₹' + finalPrice.toFixed(2) + '</strong>';
                                }
                            } else {
                                calcText += 'Type: Sub (No Comm)<br>';
                                if (gstAgreedValue) {
                                    calcText += '<span class="text-success">GST: Absorbed</span><br>';
                                    calcText += '<strong>Online: ₹' + merchantPrice.toFixed(2) + '</strong>';
                                } else {
                                    const gstAmount = merchantPrice * (gstPercent / 100);
                                    const finalPrice = merchantPrice + gstAmount;
                                    calcText += '<span class="text-warning">GST: +₹' + gstAmount.toFixed(2) + '</span><br>';
                                    calcText += '<strong>Online: ₹' + finalPrice.toFixed(2) + '</strong>';
                                }
                            }
                            return calcText;
                        }
                        return '<small class="text-muted">Enter merchant price to see calculation</small>';
                    })()}
                    </div>
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm product-discount-price"
                           data-product-id="${productId}"
                           value="${isSelected ? (selectedProducts[productId].discount_price || '') : (isExisting && product.vendor_disPrice ? product.vendor_disPrice : '')}"
                           placeholder="0.00"
                           onchange="updateProductData(${productId}, 'discount_price', this.value)"
                           onblur="validateDiscountPrice(${productId})"
                           style="min-width: 100px;">
                </td>
                <td>
                    <div class="addons-section" data-product-id="${productId}">
                        <div class="addons-container" data-product-id="${productId}" style="max-height: 150px; overflow-y: auto;">
                            ${renderAddons(productId, isExisting && !isSelected ? product : null)}
                        </div>
                      <button type="button"
                       class="btn btn-sm btn-outline-primary btn-block mt-1"
                       onclick="addAddonRow(${productId})"
                       ${!isSelected ? 'disabled' : ''}
                       style="font-size: 0.75rem;">
                       <i class="fa fa-plus"></i> Add Addon
                       </button>
                    </div>
                </td>
                <td style="vertical-align: middle;">
                 ${renderOptions(product)}
                </td>

                <td class="text-center" style="vertical-align: middle;">
                    <input type="checkbox"
                           class="form-check-input product-publish"
                           data-product-id="${productId}"
                           ${isSelected ? (selectedProducts[productId].publish ? 'checked' : '') : (isExisting && product.vendor_publish !== undefined ? (product.vendor_publish ? 'checked' : '') : 'checked')}
                           onchange="updateProductData(${productId}, 'publish', this.checked)"
                           ${!isSelected ? 'disabled' : ''}
                           style="position: relative !important; left: auto !important; top: auto !important; width: 20px !important; height: 20px !important; cursor: ${!isSelected ? 'not-allowed' : 'pointer'} !important; display: block !important; margin: 0 auto !important; opacity: ${!isSelected ? '0.5' : '1'} !important; visibility: visible !important; -webkit-appearance: checkbox !important; -moz-appearance: checkbox !important; appearance: checkbox !important; z-index: 10 !important;">
                </td>
                <td class="text-center" style="vertical-align: middle;">
                   <input type="checkbox"
                        class="form-check-input product-available"
                            data-product-id="${productId}"
                          ${isSelected ? (selectedProducts[productId].isAvailable ? 'checked' : '')
                        : (isExisting && product.vendor_isAvailable !== undefined
                            ? (product.vendor_isAvailable ? 'checked' : '')
                            : 'checked')}
                            onchange="updateProductData(${productId}, 'isAvailable', this.checked)"
                            ${!(isSelected || isExisting) ? 'disabled' : ''}
                           style="position: relative !important; left: auto !important; top: auto !important; width: 20px !important; height: 20px !important; cursor: ${!isSelected ? 'not-allowed' : 'pointer'} !important; display: block !important; margin: 0 auto !important; opacity: ${!isSelected ? '0.5' : '1'} !important; visibility: visible !important; -webkit-appearance: checkbox !important; -moz-appearance: checkbox !important; appearance: checkbox !important; z-index: 10 !important;">
                </td>
                <td style="vertical-align: middle;">
                    <div class="availability-days-cell" data-product-id="${productId}">
                        ${renderAvailabilityDays(productId, (() => {
                        if (isSelected && selectedProducts[productId] && selectedProducts[productId].available_days) {
                            return selectedProducts[productId].available_days;
                        }
                        if (isExisting && product.vendor_available_days) {
                            try {
                                return Array.isArray(product.vendor_available_days) ? product.vendor_available_days : JSON.parse(product.vendor_available_days || '[]');
                            } catch(e) { return []; }
                        }
                        return [];
                    })())}
                    </div>
                </td>
                <td style="vertical-align: middle;">
                    <div class="availability-timings-cell" data-product-id="${productId}">
                        ${renderAvailabilityTimings(productId, (() => {
                        if (isSelected && selectedProducts[productId] && selectedProducts[productId].available_timings) {
                            return selectedProducts[productId].available_timings;
                        }
                        if (isExisting && product.vendor_available_timings) {
                            try {
                                const rawTimings = typeof product.vendor_available_timings === 'object' ? product.vendor_available_timings : JSON.parse(product.vendor_available_timings || '{}');
                                // Convert new format to day-based format for display
                                if (Array.isArray(rawTimings) && rawTimings[0] && rawTimings[0].day) {
                                    const converted = {};
                                    rawTimings.forEach(item => {
                                        if (item.day && item.timeslot) {
                                            converted[item.day] = item.timeslot;
                                        }
                                    });
                                    return converted;
                                }
                                // Old format - convert
                                if (!Array.isArray(rawTimings)) {
                                    const converted = {};
                                    Object.keys(rawTimings).forEach(day => {
                                        const slots = rawTimings[day];
                                        if (Array.isArray(slots)) {
                                            converted[day] = slots.map(slot => {
                                                if (typeof slot === 'string' && slot.includes('-')) {
                                                    const parts = slot.split('-');
                                                    return { from: parts[0].trim(), to: parts[1]?.trim() || '' };
                                                }
                                                return typeof slot === 'object' ? slot : { from: '', to: '' };
                                            });
                                        }
                                    });
                                    return converted;
                                }
                                return rawTimings;
                            } catch(e) { return {}; }
                        }
                        return {};
                    })())}
                    </div>
                </td>
            `;
                    container.appendChild(row);




                })

                // Recalculate online prices for all products with merchant prices after rendering
                products.forEach(product => {
                    const productId = product.id;
                    const merchantPriceInput = document.querySelector(`.product-merchant-price[data-product-id="${productId}"]`);
                    if (merchantPriceInput && merchantPriceInput.value) {
                        // Trigger recalculation by calling updateProductData
                        updateProductData(productId, 'merchant_price', merchantPriceInput.value);
                    }
                });

                // Update summary after rendering all products
                updateSelectedProductsSummary();

                // Add select all functionality
                const selectAllCheckbox = document.getElementById('select-all-products');
                if (selectAllCheckbox) {
                    // Remove old event listener if exists
                    const newSelectAll = selectAllCheckbox.cloneNode(true);
                    selectAllCheckbox.parentNode.replaceChild(newSelectAll, selectAllCheckbox);

                    // Attach new event listener
                    document.getElementById('select-all-products').addEventListener('change', function() {
                        const checkboxes = container.querySelectorAll('.product-checkbox');
                        const isChecked = this.checked;
                        checkboxes.forEach(cb => {
                            cb.checked = isChecked;
                            cb.dispatchEvent(new Event('change', { bubbles: true }));
                        });

                    });
                }
            }

            // Render addons for a product (table format)
            function renderAddons(productId, vendorProduct) {
                let addonsToRender = [];

                // If product is selected, use selectedProducts data
                if (selectedProducts[productId] && selectedProducts[productId].addons && selectedProducts[productId].addons.length > 0) {
                    addonsToRender = selectedProducts[productId].addons;
                } else if (vendorProduct && vendorProduct.vendor_addOnsTitle && vendorProduct.vendor_addOnsPrice) {
                    // If product exists but not selected, use vendor addons for display only (don't add to selectedProducts)
                    const titles = Array.isArray(vendorProduct.vendor_addOnsTitle) ? vendorProduct.vendor_addOnsTitle : (vendorProduct.vendor_addOnsTitle ? JSON.parse(vendorProduct.vendor_addOnsTitle) : []);
                    const prices = Array.isArray(vendorProduct.vendor_addOnsPrice) ? vendorProduct.vendor_addOnsPrice : (vendorProduct.vendor_addOnsPrice ? JSON.parse(vendorProduct.vendor_addOnsPrice) : []);
                    for (let i = 0; i < Math.max(titles.length, prices.length); i++) {
                        if (titles[i] && prices[i]) {
                            addonsToRender.push({
                                title: titles[i],
                                price: prices[i]
                            });
                        }
                    }
                }

                // Show ONE empty row ONLY if product is selected AND addons array truly empty
                if (
                    addonsToRender.length === 0 &&
                    selectedProducts[productId]
                ) {
                    addonsToRender.push({ title: '', price: '' });
                }



                let html = '';
                addonsToRender.forEach((addon, index) => {
                    // Escape HTML to prevent XSS
                    const title = String(addon.title || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    const price = String(addon.price || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    html += `
                <div class="addon-row mb-1 d-flex align-items-center">
                    <input type="text"
                           class="form-control form-control-sm mb-1 addon-title"
                           placeholder="Addon title"
                           value="${title}"
                           onchange="updateAddon(${productId}, ${index}, 'title', this.value)"
                           style="font-size: 0.75rem; flex: 1; margin-right: 5px;">
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm addon-price"
                           placeholder="Price"
                           value="${price}"
                           onchange="updateAddon(${productId}, ${index}, 'price', this.value)"
                           style="font-size: 0.75rem; width: 70px; margin-right: 5px;">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeAddon(${productId}, ${index})" style="font-size: 0.75rem;">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            `;
                });
                return html || '<div class="addon-row mb-1"><input type="text" class="form-control form-control-sm mb-1 addon-title" placeholder="Addon title" style="font-size: 0.75rem;"><input type="number" step="0.01" class="form-control form-control-sm addon-price" placeholder="Price" style="font-size: 0.75rem;"></div>';
            }

            function renderOptions(product) {
                const productId = product.id;

                // Priority: selected → vendor → master
                let options = [];

                // 1️⃣ Selected (in-memory)
                if (selectedProducts[productId] && Array.isArray(selectedProducts[productId].options)) {
                    options = selectedProducts[productId].options;

                    // 2️⃣ Vendor saved options
                } else if (product.vendor_options) {
                    try {
                        options = Array.isArray(product.vendor_options)
                            ? product.vendor_options
                            : JSON.parse(product.vendor_options);
                    } catch (e) {
                        options = [];
                    }

                    // 3️⃣ Master options
                } else if (Array.isArray(product.options)) {
                    options = product.options;
                }

                const selectedCount = options.filter(o => o.is_available).length;

                let label = 'Set Options';

                if (selectedCount === 1) {
                    label = options.find(o => o.is_available)?.title || '1 option';
                } else if (selectedCount > 1) {
                    label = `${selectedCount} options`;
                }

                return `
        <button type="button"
            class="btn btn-sm btn-outline-secondary options-btn"
            data-product-id="${productId}"
            onclick="openOptionsModal('${productId}')"
            title="${selectedCount} option(s) selected"
            style="font-size:0.75rem;width:100%;">
            <i class="fa fa-list mr-1"></i>${label}
        </button>
    `;
            }

            // Render availability days (compact button with modal/popup)
            function renderAvailabilityDays(productId, availableDays) {
                const days = Array.isArray(availableDays) ? availableDays : [];
                const daysText = days.length > 0 ? days.join(', ') : 'Not set';
                const daysShort = days.length > 0 ? (days.length <= 2 ? days.join(', ') : days.length + ' days') : 'Set Days';

                return `
                    <button type="button"
                            class="btn btn-sm btn-outline-primary availability-days-btn"
                            data-product-id="${productId}"
                            onclick="openAvailabilityModal(${productId})"
                            title="${daysText}"
                            style="font-size: 0.75rem; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <i class="fa fa-calendar mr-1"></i>${daysShort}
                    </button>
                `;
            }

            // Render availability timings (compact display)
            function renderAvailabilityTimings(productId, availableTimings) {
                // Handle both old and new formats (backward compatibility)
                let timings = {};
                if (Array.isArray(availableTimings)) {
                    // New format: [{day: "Monday", timeslot: [{from: "09:00", to: "12:00"}]}]
                    availableTimings.forEach(item => {
                        if (item.day && item.timeslot) {
                            timings[item.day] = item.timeslot;
                        }
                    });
                } else if (typeof availableTimings === 'object' && availableTimings !== null) {
                    // Old format: {"Monday": ["09:00-12:00"]} or already converted
                    timings = availableTimings;
                }

                const timingCount = Object.keys(timings).length;
                const slotsCount = Object.values(timings).reduce((sum, slots) => {
                    return sum + (Array.isArray(slots) ? slots.length : 0);
                }, 0);

                let displayText = 'Not set';
                if (timingCount > 0 && slotsCount > 0) {
                    displayText = `${slotsCount} slot${slotsCount !== 1 ? 's' : ''}`;
                }

                return `
                    <small class="text-muted" style="font-size: 0.75rem;" title="${JSON.stringify(timings)}">${displayText}</small>
                `;
            }

            // Toggle product selection
            window.toggleProductSelection = function(productId) {
                try {
                    const checkbox = document.querySelector(`.product-checkbox[data-product-id="${productId}"]`);
                    const row = document.querySelector(`.product-row[data-product-id="${productId}"]`);

                    if (!checkbox) {
                        console.error('Checkbox not found for product:', productId);
                        return;
                    }

                    if (checkbox.checked) {
                        // Add to selected products
                        const merchantPriceInput = document.querySelector(`.product-merchant-price[data-product-id="${productId}"]`);
                        const discountPriceInput = document.querySelector(`.product-discount-price[data-product-id="${productId}"]`);
                        const publishCheckbox = document.querySelector(`.product-publish[data-product-id="${productId}"]`);
                        const availableCheckbox = document.querySelector(`.product-available[data-product-id="${productId}"]`);
                        const isExisting = row ? row.hasAttribute('data-is-existing') : false;

                        // Get current values from inputs (merchant_price and discount_price are always editable)
                        const merchantPrice = merchantPriceInput ? (merchantPriceInput.value || '') : '';
                        const discountPrice = discountPriceInput ? (discountPriceInput.value || '') : '';
                        const publish = publishCheckbox ? publishCheckbox.checked : true;
                        const isAvailable = availableCheckbox ? availableCheckbox.checked : true;
                        let existingOptions = [];

// Priority: selected → vendor → master
                        if (selectedProducts[productId]?.options) {
                            existingOptions = selectedProducts[productId].options;
                        } else if (currentProductsData[productId]?.vendor_options) {
                            try {
                                existingOptions = Array.isArray(currentProductsData[productId].vendor_options)
                                    ? currentProductsData[productId].vendor_options
                                    : JSON.parse(currentProductsData[productId].vendor_options);
                            } catch (e) {
                                existingOptions = [];
                            }
                        } else if (Array.isArray(currentProductsData[productId]?.options)) {
                            existingOptions = currentProductsData[productId].options.map(opt => ({
                                ...opt,              // 🔥 clone full master structure
                                is_available: false  // default unchecked
                            }));
                        }


                        // Calculate online price based on subscription and GST (only if merchant_price is provided)
                        const merchantPriceNum = parseFloat(merchantPrice);
                        let onlinePrice = '';

                        if (!isNaN(merchantPriceNum) && merchantPriceNum > 0) {
                            // Get subscription info from global scope
                            const hasSub = typeof hasSubscription !== 'undefined' ? hasSubscription : false;
                            const applyPercent = typeof applyPercentage !== 'undefined' ? applyPercentage : 30;
                            const planTypeValue = typeof planType !== 'undefined' ? planType : 'commission';
                            const gstAgreedValue = typeof gstAgreed !== 'undefined' ? gstAgreed : false;
                            const gstPercent = 5;

                            // Determine if it's commission-based or subscription-based
                            const isCommissionBased = !hasSub || planTypeValue === 'commission';

                            if (isCommissionBased) {
                                // Scenario 1: Commission-Based Model
                                const commission = merchantPriceNum * (applyPercent / 100);
                                const priceBeforeGst = merchantPriceNum + commission;

                                if (gstAgreedValue) {
                                    // Case 1: Merchant AGREED for GST - Platform absorbs GST
                                    onlinePrice = priceBeforeGst.toFixed(2);
                                } else {
                                    // Case 2: Merchant NOT AGREED for GST - GST is 5% of Merchant Price
                                    const gstAmount = merchantPriceNum * (gstPercent / 100);
                                    onlinePrice = (priceBeforeGst + gstAmount).toFixed(2);
                                }
                            } else {
                                // Scenario 2: Subscription-Based Model (No Commission)
                                if (gstAgreedValue) {
                                    // Case 1: Merchant AGREED for GST - Platform absorbs GST
                                    onlinePrice = merchantPriceNum.toFixed(2);
                                } else {
                                    // Case 2: Merchant NOT AGREED for GST - Add GST to customer price
                                    onlinePrice = (merchantPriceNum + (merchantPriceNum * (gstPercent / 100))).toFixed(2);
                                }
                            }
                        }

                        // Load vendor addons if product exists and not already loaded
                        let addons = selectedProducts[productId]?.addons || [];
                        if (isExisting && addons.length === 0 && currentProductsData[productId]) {
                            const vendorProduct = currentProductsData[productId];
                            if (vendorProduct.vendor_addOnsTitle && vendorProduct.vendor_addOnsPrice) {
                                const titles = Array.isArray(vendorProduct.vendor_addOnsTitle) ? vendorProduct.vendor_addOnsTitle : (vendorProduct.vendor_addOnsTitle ? JSON.parse(vendorProduct.vendor_addOnsTitle) : []);
                                const prices = Array.isArray(vendorProduct.vendor_addOnsPrice) ? vendorProduct.vendor_addOnsPrice : (vendorProduct.vendor_addOnsPrice ? JSON.parse(vendorProduct.vendor_addOnsPrice) : []);
                                addons = [];
                                for (let i = 0; i < Math.max(titles.length, prices.length); i++) {
                                    if (titles[i] && prices[i]) {
                                        addons.push({
                                            title: titles[i],
                                            price: prices[i]
                                        });
                                    }
                                }
                            }
                        }

                        // Load availability data if product exists
                        let availableDays = selectedProducts[productId]?.available_days || [];
                        let availableTimings = selectedProducts[productId]?.available_timings || {};
                        if (isExisting && (availableDays.length === 0 || Object.keys(availableTimings).length === 0) && currentProductsData[productId]) {
                            const vendorProduct = currentProductsData[productId];
                            if (vendorProduct.vendor_available_days) {
                                availableDays = Array.isArray(vendorProduct.vendor_available_days) ? vendorProduct.vendor_available_days : JSON.parse(vendorProduct.vendor_available_days || '[]');
                            }
                            if (vendorProduct.vendor_available_timings) {
                                const rawTimings = typeof vendorProduct.vendor_available_timings === 'object' ? vendorProduct.vendor_available_timings : JSON.parse(vendorProduct.vendor_available_timings || '{}');

                                // Convert to day-based format for modal (handle both old and new formats)
                                if (Array.isArray(rawTimings) && rawTimings[0] && rawTimings[0].day) {
                                    // New format: [{day: "Monday", timeslot: [{from: "09:00", to: "12:00"}]}]
                                    availableTimings = {};
                                    rawTimings.forEach(item => {
                                        if (item.day && item.timeslot) {
                                            availableTimings[item.day] = item.timeslot;
                                        }
                                    });
                                } else {
                                    // Old format: {"Monday": ["09:00-12:00"]} - convert to new format
                                    availableTimings = {};
                                    Object.keys(rawTimings).forEach(day => {
                                        const slots = rawTimings[day];
                                        if (Array.isArray(slots)) {
                                            availableTimings[day] = slots.map(slot => {
                                                if (typeof slot === 'string' && slot.includes('-')) {
                                                    const parts = slot.split('-');
                                                    return { from: parts[0].trim(), to: parts[1]?.trim() || '' };
                                                }
                                                return typeof slot === 'object' ? slot : { from: '', to: '' };
                                            });
                                        }
                                    });
                                }
                            }
                        }

                        const vendorProductId = row.getAttribute('data-vendor-product-id') || null;

                        selectedProducts[productId] = {
                            master_product_id: productId,
                            vendor_product_id: vendorProductId,
                            merchant_price: merchantPrice,
                            online_price: onlinePrice,
                            discount_price: discountPrice,
                            publish: publish,
                            isAvailable: isAvailable,
                            addons: addons,
                            available_days: availableDays,
                            available_timings: availableTimings,
                            options: existingOptions
                        };


                        // Update online price input field
                        const onlinePriceInput = document.querySelector(`.product-online-price[data-product-id="${productId}"]`);
                        if (onlinePriceInput) {
                            onlinePriceInput.value = onlinePrice;
                        }

                        // Add to selection order if not already there
                        if (!selectionOrder.includes(productId)) {
                            selectionOrder.push(productId);
                        }

                        // Re-render addons if they were loaded
                        if (isExisting && addons.length > 0) {
                            const addonsContainer = document.querySelector(`.addons-container[data-product-id="${productId}"]`);
                            if (addonsContainer) {
                                addonsContainer.innerHTML = renderAddons(productId, null);
                            }
                        }

                        // Enable publish and available checkboxes (merchant_price and discount_price are always editable)
                        if (publishCheckbox) publishCheckbox.disabled = false;
                        if (availableCheckbox) availableCheckbox.disabled = false;

                        // Enable addon button
                        const addonButton = document.querySelector(`.addons-section[data-product-id="${productId}"] button`);
                        if (addonButton) addonButton.disabled = false;

                        // Update row highlighting: selected (blue) overrides existing (green)
                        if (row) {
                            row.classList.remove('table-success');
                            row.classList.add('table-primary');
                        }
                    } else {
                        // Remove from selected products
                        delete selectedProducts[productId];


                        // Remove from selection order
                        selectionOrder = selectionOrder.filter(id => id !== productId);

                        // Disable publish and available checkboxes (merchant_price and discount_price remain editable)
                        const publishCheckbox = document.querySelector(`.product-publish[data-product-id="${productId}"]`);
                        const availableCheckbox = document.querySelector(`.product-available[data-product-id="${productId}"]`);
                        const isExisting = row ? row.hasAttribute('data-is-existing') : false;

                        if (publishCheckbox) publishCheckbox.disabled = true;
                        if (availableCheckbox) availableCheckbox.disabled = true;

                        // Disable addon button
                        const addonButton = document.querySelector(`.addons-section[data-product-id="${productId}"] button`);
                        if (addonButton) addonButton.disabled = true;

                        // Update row highlighting: show green if existing, otherwise no highlight
                        if (row) {
                            row.classList.remove('table-primary');
                            if (isExisting) {
                                row.classList.add('table-success');
                            }
                        }
                    }

                    updateSelectedProductsSummary();
                } catch (error) {
                    console.error('Error in toggleProductSelection:', error);
                }
            };

            // Update price calculation display
            function updatePriceCalculation(productId, merchantPrice, onlinePrice, hasSub, applyPercent, planTypeValue, gstAgreedValue) {
                // Find or create calculation display element
                let calcDisplay = document.querySelector(`.price-calculation[data-product-id="${productId}"]`);
                if (!calcDisplay) {
                    // Create calculation display element
                    const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                    if (row) {
                        // Add calculation cell after online price column
                        const onlinePriceCell = row.querySelector('.product-online-price[data-product-id="${productId}"]')?.closest('td');
                        if (onlinePriceCell) {
                            const calcCell = document.createElement('td');
                            calcCell.className = 'price-calculation-cell';
                            calcCell.innerHTML = '<div class="price-calculation" data-product-id="' + productId + '" style="font-size: 0.75rem; max-width: 200px;"></div>';
                            onlinePriceCell.parentNode.insertBefore(calcCell, onlinePriceCell.nextSibling);
                            calcDisplay = calcCell.querySelector('.price-calculation');
                        }
                    }
                }

                if (calcDisplay && merchantPrice > 0) {
                    const isCommissionBased = !hasSub || planTypeValue === 'commission';
                    let calcText = '<strong>Calculation:</strong><br>';
                    calcText += 'Merchant: ₹' + merchantPrice.toFixed(2) + '<br>';

                    if (isCommissionBased) {
                        const commission = merchantPrice * (applyPercent / 100);
                        const priceBeforeGst = merchantPrice + commission;
                        calcText += 'Type: Commission (' + applyPercent + '%)<br>';
                        calcText += 'Commission: ₹' + commission.toFixed(2) + '<br>';
                        calcText += 'Before GST: ₹' + priceBeforeGst.toFixed(2) + '<br>';

                        if (gstAgreedValue) {
                            calcText += '<span class="text-success">GST: Absorbed by platform</span><br>';
                        } else {
                            // GST is 5% of Merchant Price (not of price before GST)
                            const gstAmount = merchantPrice * (gstPercentage / 100);
                            calcText += '<span class="text-warning">GST (5%): +₹' + gstAmount.toFixed(2) + '</span><br>';
                        }
                    } else {
                        calcText += 'Type: Subscription (No Commission)<br>';
                        if (gstAgreedValue) {
                            calcText += '<span class="text-success">GST: Absorbed by platform</span><br>';
                        } else {
                            const gstAmount = merchantPrice * (gstPercentage / 100);
                            calcText += '<span class="text-warning">GST (5%): +₹' + gstAmount.toFixed(2) + '</span><br>';
                        }
                    }

                    calcText += '<strong>Online Price: ₹' + onlinePrice.toFixed(2) + '</strong>';
                    calcDisplay.innerHTML = calcText;
                }
            }

            // Update product data
            window.updateProductData = function(productId, field, value) {
                // If product is selected, update its data
                if (selectedProducts[productId]) {
                    if (field === 'publish' || field === 'isAvailable') {
                        selectedProducts[productId][field] = value === true || value === 'true' || value === 1 || value === '1';
                    } else if (field === 'merchant_price') {
                        selectedProducts[productId][field] = value;

                        // Calculate online price based on subscription and GST (only if merchant_price is provided)
                        // Only auto-calculate if online price is empty or 0 (allows manual override)
                        const merchantPriceNum = parseFloat(value);
                        const onlinePriceInput = document.querySelector(`.product-online-price[data-product-id="${productId}"]`);
                        const currentOnlinePrice = onlinePriceInput ? parseFloat(onlinePriceInput.value) || 0 : 0;

                        if (!isNaN(merchantPriceNum) && merchantPriceNum > 0) {
                            // Only auto-calculate if online price is empty or 0
                            if (currentOnlinePrice === 0) {
                                let onlinePrice = 0;
                                const hasSub = typeof hasSubscription !== 'undefined' ? hasSubscription : false;
                                const applyPercent = typeof applyPercentage !== 'undefined' ? applyPercentage : 30;
                                const planTypeValue = typeof planType !== 'undefined' ? planType : 'commission';
                                const gstAgreedValue = typeof gstAgreed !== 'undefined' ? gstAgreed : false;

                                // Determine if it's commission-based or subscription-based
                                const isCommissionBased = !hasSub || planTypeValue === 'commission';

                                if (isCommissionBased) {
                                    // Scenario 1: Commission-Based Model
                                    const commission = merchantPriceNum * (applyPercent / 100);
                                    const priceBeforeGst = merchantPriceNum + commission;

                                    if (gstAgreedValue) {
                                        // Case 1: Merchant AGREED for GST - Platform absorbs GST
                                        onlinePrice = priceBeforeGst;
                                    } else {
                                        // Case 2: Merchant NOT AGREED for GST - GST is 5% of Merchant Price
                                        const gstAmount = merchantPriceNum * (gstPercentage / 100);
                                        onlinePrice = priceBeforeGst + gstAmount;
                                    }
                                } else {
                                    // Scenario 2: Subscription-Based Model (No Commission)
                                    if (gstAgreedValue) {
                                        // Case 1: Merchant AGREED for GST - Platform absorbs GST
                                        onlinePrice = merchantPriceNum;
                                    } else {
                                        // Case 2: Merchant NOT AGREED for GST - Add GST to customer price
                                        onlinePrice = merchantPriceNum + (merchantPriceNum * (gstPercentage / 100));
                                    }
                                }

                                selectedProducts[productId].online_price = onlinePrice.toFixed(2);

                                // Update online price input field
                                if (onlinePriceInput) {
                                    onlinePriceInput.value = onlinePrice.toFixed(2);

                                    // Update calculation display
                                    updatePriceCalculation(productId, merchantPriceNum, onlinePrice, hasSub, applyPercent, planTypeValue, gstAgreedValue);

                                    // Re-validate discount price when online price changes
                                    if (selectedProducts[productId].discount_price) {
                                        validateDiscountPrice(productId);
                                    }
                                }
                            } else {
                                // Online price has manual value - calculate correct value and update calculation display with it
                                const hasSub = typeof hasSubscription !== 'undefined' ? hasSubscription : false;
                                const applyPercent = typeof applyPercentage !== 'undefined' ? applyPercentage : 30;
                                const planTypeValue = typeof planType !== 'undefined' ? planType : 'commission';
                                const gstAgreedValue = typeof gstAgreed !== 'undefined' ? gstAgreed : false;

                                // Calculate the correct online price
                                let calculatedOnlinePrice = 0;
                                const isCommissionBased = !hasSub || planTypeValue === 'commission';

                                if (isCommissionBased) {
                                    const commission = merchantPriceNum * (applyPercent / 100);
                                    const priceBeforeGst = merchantPriceNum + commission;
                                    if (gstAgreedValue) {
                                        calculatedOnlinePrice = priceBeforeGst;
                                    } else {
                                        const gstAmount = merchantPriceNum * (gstPercentage / 100);
                                        calculatedOnlinePrice = priceBeforeGst + gstAmount;
                                    }
                                } else {
                                    if (gstAgreedValue) {
                                        calculatedOnlinePrice = merchantPriceNum;
                                    } else {
                                        calculatedOnlinePrice = merchantPriceNum + (merchantPriceNum * (gstPercentage / 100));
                                    }
                                }

                                // Update calculation display with calculated value (shows what it should be)
                                updatePriceCalculation(productId, merchantPriceNum, calculatedOnlinePrice, hasSub, applyPercent, planTypeValue, gstAgreedValue);

                                // Also update the input field if the current value seems wrong (differs significantly from calculated)
                                // This handles cases where old incorrect values are in the input field
                                // Allow up to 10% difference for manual edits, but update if way off
                                const difference = Math.abs(currentOnlinePrice - calculatedOnlinePrice);
                                const percentDifference = (difference / calculatedOnlinePrice) * 100;
                                if (onlinePriceInput && currentOnlinePrice > 0 && percentDifference > 10) {
                                    // Value differs significantly from calculated value, update it
                                    onlinePriceInput.value = calculatedOnlinePrice.toFixed(2);
                                    selectedProducts[productId].online_price = calculatedOnlinePrice.toFixed(2);
                                    if (selectedProducts[productId].discount_price) {
                                        validateDiscountPrice(productId);
                                    }
                                }
                            }
                        } else {
                            // If merchant_price is empty, clear online_price only if it was auto-calculated (0)
                            if (currentOnlinePrice === 0) {
                                selectedProducts[productId].online_price = '';
                                if (onlinePriceInput) {
                                    onlinePriceInput.value = '';
                                }
                            }
                        }
                    } else if (field === 'online_price') {
                        // Online price was manually edited - update the stored value and calculation display
                        const onlinePriceNum = parseFloat(value) || 0;
                        selectedProducts[productId].online_price = onlinePriceNum > 0 ? onlinePriceNum.toFixed(2) : '';

                        // Update calculation display
                        const merchantPriceInput = document.querySelector(`.product-merchant-price[data-product-id="${productId}"]`);
                        const merchantPriceNum = merchantPriceInput ? parseFloat(merchantPriceInput.value) || 0 : 0;

                        if (merchantPriceNum > 0 && onlinePriceNum > 0) {
                            const hasSub = typeof hasSubscription !== 'undefined' ? hasSubscription : false;
                            const applyPercent = typeof applyPercentage !== 'undefined' ? applyPercentage : 30;
                            const planTypeValue = typeof planType !== 'undefined' ? planType : 'commission';
                            const gstAgreedValue = typeof gstAgreed !== 'undefined' ? gstAgreed : false;
                            updatePriceCalculation(productId, merchantPriceNum, onlinePriceNum, hasSub, applyPercent, planTypeValue, gstAgreedValue);
                        }

                        // Re-validate discount price when online price changes
                        const discountPriceInput = document.querySelector(`.product-discount-price[data-product-id="${productId}"]`);
                        if (discountPriceInput) {
                            validateDiscountPrice(productId);
                        }
                    } else if (field === 'discount_price') {
                        // Validate discount price: it should not be greater than online price
                        const discountPriceNum = parseFloat(value) || 0;
                        const onlinePriceInput = document.querySelector(`.product-online-price[data-product-id="${productId}"]`);
                        const discountPriceInput = document.querySelector(`.product-discount-price[data-product-id="${productId}"]`);

                        if (onlinePriceInput && discountPriceInput) {
                            const onlinePriceNum = parseFloat(onlinePriceInput.value) || 0;

                            if (discountPriceNum > 0 && onlinePriceNum > 0 && discountPriceNum > onlinePriceNum) {
                                // Get product name from the table row
                                const productRow = discountPriceInput.closest('tr');
                                const productNameCell = productRow ? productRow.querySelector('td:nth-child(3) strong') : null;
                                const productName = productNameCell ? productNameCell.textContent.trim() : 'this product';

                                // Show error message with product name
                                const errorMsg = `Discount price cannot be greater than online price for product "${productName}".`;
                                alert(errorMsg);

                                // Reset to previous value or 0
                                const previousValue = selectedProducts[productId].discount_price || '0';
                                discountPriceInput.value = previousValue;
                                selectedProducts[productId][field] = previousValue;

                                // Add visual feedback (red border)
                                discountPriceInput.style.borderColor = '#dc3545';
                                setTimeout(() => {
                                    discountPriceInput.style.borderColor = '';
                                }, 3000);

                                return; // Don't update the value
                            } else {
                                // Remove error styling if validation passes
                                discountPriceInput.style.borderColor = '';
                            }
                        }

                        selectedProducts[productId][field] = value;
                    } else {
                        selectedProducts[productId][field] = value;
                    }
                }
                // If product is not selected, values are stored in inputs but won't be saved until checkbox is checked
            };

            window.addAddonRow = function (productId) {

                if (!selectedProducts[productId]) {
                    alert('Please select this product first.');
                    return;
                }

                if (!Array.isArray(selectedProducts[productId].addons)) {
                    selectedProducts[productId].addons = [];
                }

                // 🔥 CAPTURE CURRENT INPUT VALUES BEFORE ADDING NEW ROW
                const container = document.querySelector(
                    `.addons-container[data-product-id="${productId}"]`
                );

                if (container) {
                    const rows = container.querySelectorAll('.addon-row');
                    const captured = [];

                    rows.forEach(row => {
                        const title = row.querySelector('.addon-title')?.value.trim();
                        const price = row.querySelector('.addon-price')?.value.trim();

                        if (title || price) {
                            captured.push({ title, price });
                        }
                    });

                    selectedProducts[productId].addons = captured;
                }

                // ✅ ADD ONE NEW EMPTY ROW
                selectedProducts[productId].addons.push({ title: '', price: '' });

                if (container) {
                    container.innerHTML = renderAddons(productId, null);
                }
            };


            // Validate discount price against online price
            window.validateDiscountPrice = function(productId) {
                const discountPriceInput = document.querySelector(`.product-discount-price[data-product-id="${productId}"]`);
                const onlinePriceInput = document.querySelector(`.product-online-price[data-product-id="${productId}"]`);

                if (discountPriceInput && onlinePriceInput) {
                    const discountPriceNum = parseFloat(discountPriceInput.value) || 0;
                    const onlinePriceNum = parseFloat(onlinePriceInput.value) || 0;

                    if (discountPriceNum > 0 && onlinePriceNum > 0 && discountPriceNum > onlinePriceNum) {
                        // Get product name from the table row
                        const productRow = discountPriceInput.closest('tr');
                        const productNameCell = productRow ? productRow.querySelector('td:nth-child(3) strong') : null;
                        const productName = productNameCell ? productNameCell.textContent.trim() : 'this product';

                        // Show error message with product name
                        const errorMsg = `Discount price cannot be greater than online price for product "${productName}".`;
                        alert(errorMsg);

                        // Reset to 0 or previous valid value
                        discountPriceInput.value = '0';
                        if (selectedProducts[productId]) {
                            selectedProducts[productId].discount_price = '0';
                        }

                        // Add visual feedback (red border)
                        discountPriceInput.style.borderColor = '#dc3545';
                        setTimeout(() => {
                            discountPriceInput.style.borderColor = '';
                        }, 3000);

                        return false;
                    } else {
                        // Remove error styling if validation passes
                        discountPriceInput.style.borderColor = '';
                        return true;
                    }
                }
                return true;
            };

            // Update addon
            window.updateAddon = function(productId, index, field, value) {
                // Only update if product is actually selected (checkbox checked)
                const checkbox = document.querySelector(`.product-checkbox[data-product-id="${productId}"]`);
                if (!checkbox || !checkbox.checked || !selectedProducts[productId]) {
                    return;
                }

                // Initialize addons array if it doesn't exist
                if (!selectedProducts[productId].addons) {
                    selectedProducts[productId].addons = [];
                }


                // Ensure the addon at this index exists
                while (selectedProducts[productId].addons.length <= index) {
                    selectedProducts[productId].addons.push({ title: '', price: '' });
                }

                // Update the field
                selectedProducts[productId].addons[index][field] = value;

                // If this addon now has both title and price, show the remove button
                const container = document.querySelector(`.addons-container[data-product-id="${productId}"]`);
                if (container) {
                    const row = container.querySelectorAll('.addon-row')[index];
                    if (row) {
                        const removeBtn = row.querySelector('button');
                        const titleInput = row.querySelector('.addon-title');
                        const priceInput = row.querySelector('.addon-price');
                        if (removeBtn && titleInput && priceInput) {
                            const hasTitle = titleInput.value.trim() !== '';
                            const hasPrice = priceInput.value.trim() !== '';
                            if (hasTitle || hasPrice) {
                                removeBtn.style.visibility = 'visible';
                                removeBtn.setAttribute('onclick', `removeAddon(${productId}, ${index})`);
                            }
                        }
                    }
                }
            };

            // Remove addon
            window.removeAddon = function(productId, index) {
                if (selectedProducts[productId] && selectedProducts[productId].addons) {
                    selectedProducts[productId].addons.splice(index, 1);
                    const container = document.querySelector(`.addons-container[data-product-id="${productId}"]`);
                    if (container) {
                        container.innerHTML = renderAddons(productId, null);

                    }
                }
            };



            function updateSelectedProductsSummary() {
                const summaryDiv = document.getElementById('selected-products-summary');
                const countElement = document.getElementById('selected-count');
                const saveCountElement = document.getElementById('save-count');

                // ✅ ONLY SOURCE OF TRUTH = CHECKED CHECKBOXES
                const checkedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
                const count = checkedCheckboxes.length;

                // 🔥 FORCE UI SYNC
                countElement.textContent = count;
                saveCountElement.textContent = count;

                if (count === 0) {
                    summaryDiv.style.display = 'none';
                } else {
                    summaryDiv.style.display = 'block';
                }
            }


            // Save selected products
            const saveButton = document.getElementById('save-selected-products');
            if (saveButton) {
                saveButton.addEventListener('click', function() {
                    const count = Object.keys(selectedProducts).length;

                    if (count === 0) {
                        alert('Please select at least one product by checking the checkbox.');
                        return;
                    }

                    // Validate all selected products have prices
                    let hasErrors = false;
                    let errorMessages = [];
                    let productNames = [];

                    Object.keys(selectedProducts).forEach(productId => {
                        const product = selectedProducts[productId];
                        const productName = document.querySelector(`.product-row[data-product-id="${productId}"]`)?.querySelector('td:nth-child(3) strong')?.textContent || `Product ID ${productId}`;

                        if (!product.merchant_price || parseFloat(product.merchant_price) <= 0) {
                            hasErrors = true;
                            errorMessages.push(`${productName} needs a valid merchant price.`);
                        } else {
                            productNames.push(productName);
                        }
                    });

                    if (hasErrors) {
                        alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
                        return;
                    }

                    // Confirm before saving
                    const confirmMessage = `Are you sure you want to add ${count} product(s) to your menu?\n\nProducts:\n${productNames.join('\n')}`;
                    if (!confirm(confirmMessage)) {
                        return;
                    }

                    // Disable button to prevent double submission
                    this.disabled = true;
                    this.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i> Saving...';

                    // Prepare form data
                    const form = document.getElementById('bulk-products-form');
                    const inputsContainer = document.getElementById('selected-products-inputs');
                    inputsContainer.innerHTML = '';

                    // Sort selected products by selection order
                    const sortedProductIds = selectionOrder.filter(id => selectedProducts.hasOwnProperty(id));
                    // Add any products that are selected but not in selectionOrder (shouldn't happen, but safety)
                    Object.keys(selectedProducts).forEach(id => {
                        if (!sortedProductIds.includes(id)) {
                            sortedProductIds.push(id);
                        }
                    });

                    sortedProductIds.forEach((productId, index) => {
                        const product = selectedProducts[productId];

                        // 🔥 CRITICAL: submit vendor_product_id FIRST (prevents duplicate insert)
                        if (product.vendor_product_id) {
                            const vpInput = document.createElement('input');
                            vpInput.type = 'hidden';
                            vpInput.name = `selected_products[${index}][vendor_product_id]`;
                            vpInput.value = product.vendor_product_id;
                            inputsContainer.appendChild(vpInput);
                        }


                        // Get latest values from inputs (merchant_price, online_price and discount_price are always editable)
                        const merchantPriceInput = document.querySelector(`.product-merchant-price[data-product-id="${productId}"]`);
                        const onlinePriceInput = document.querySelector(`.product-online-price[data-product-id="${productId}"]`);
                        const discountPriceInput = document.querySelector(`.product-discount-price[data-product-id="${productId}"]`);

                        // Update product data with latest input values
                        if (merchantPriceInput) {
                            product.merchant_price = merchantPriceInput.value || '';
                        }
                        if (onlinePriceInput) {
                            product.online_price = onlinePriceInput.value || '';
                        }
                        if (discountPriceInput) {
                            product.discount_price = discountPriceInput.value || '0';
                        }

                        // Capture latest addon values from DOM inputs
                        const addonsContainer = document.querySelector(`.addons-container[data-product-id="${productId}"]`);
                        const capturedAddons = [];
                        if (addonsContainer) {
                            const addonRows = addonsContainer.querySelectorAll('.addon-row');
                            addonRows.forEach(row => {
                                const titleInput = row.querySelector('.addon-title');
                                const priceInput = row.querySelector('.addon-price');

                                if (titleInput && priceInput) {
                                    const title = titleInput.value.trim();
                                    const price = priceInput.value.trim();

                                    // Only add if both title and price are provided and not empty
                                    if (title && price && !isNaN(parseFloat(price)) && parseFloat(price) > 0) {
                                        capturedAddons.push({
                                            title: title,
                                            price: price
                                        });
                                    }
                                }
                            });
                        }
                        // Update product addons with captured values
                        product.addons = capturedAddons;

                        // Create hidden inputs for each product
                        Object.keys(product).forEach(key => {

                            // 1️⃣ OPTIONS
                            if (key === 'options') {

                                if (Array.isArray(product.options) && product.options.length > 0) {

                                    // 🔥 Only keep selected options
                                    const selectedOptions = product.options.filter(opt => opt.is_available === true);

                                    selectedOptions.forEach((opt, optIndex) => {

                                        const input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = `selected_products[${index}][options][${optIndex}]`;
                                        input.value = JSON.stringify(opt);

                                        inputsContainer.appendChild(input);

                                    });

                                }
                            } else if (key === 'addons') {
                                if (product.addons && product.addons.length > 0) {
                                    product.addons.forEach(addon => {
                                        if (addon.title && addon.price) {
                                            const titleInput = document.createElement('input');
                                            titleInput.type = 'hidden';
                                            titleInput.name = `selected_products[${index}][addons_title][]`;
                                            titleInput.value = addon.title;
                                            inputsContainer.appendChild(titleInput);

                                            const priceInput = document.createElement('input');
                                            priceInput.type = 'hidden';
                                            priceInput.name = `selected_products[${index}][addons_price][]`;
                                            priceInput.value = addon.price;
                                            inputsContainer.appendChild(priceInput);
                                        }
                                    });
                                }

                                // 3️⃣ AVAILABILITY
                            } else if (key === 'available_days' || key === 'available_timings') {

                                if (key === 'available_days' && Array.isArray(product.available_days)) {
                                    product.available_days.forEach(day => {
                                        const input = document.createElement('input');
                                        input.type = 'hidden';
                                        input.name = `selected_products[${index}][available_days][]`;
                                        input.value = day;
                                        inputsContainer.appendChild(input);
                                    });
                                }

                                if (key === 'available_timings' && typeof product.available_timings === 'object') {
                                    Object.keys(product.available_timings).forEach(day => {
                                        product.available_timings[day].forEach((slot, slotIndex) => {
                                            if (slot.from && slot.to) {
                                                const fromInput = document.createElement('input');
                                                fromInput.type = 'hidden';
                                                fromInput.name = `selected_products[${index}][available_timings][${day}][${slotIndex}][from]`;
                                                fromInput.value = slot.from;
                                                inputsContainer.appendChild(fromInput);

                                                const toInput = document.createElement('input');
                                                toInput.type = 'hidden';
                                                toInput.name = `selected_products[${index}][available_timings][${day}][${slotIndex}][to]`;
                                                toInput.value = slot.to;
                                                inputsContainer.appendChild(toInput);
                                            }
                                        });
                                    });
                                }

                                // 4️⃣ EVERYTHING ELSE
                            } else {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = `selected_products[${index}][${key}]`;

                                if (key === 'publish' || key === 'isAvailable') {
                                    input.value = product[key] ? '1' : '0';
                                } else {
                                    input.value = product[key] || '';
                                }

                                inputsContainer.appendChild(input);
                            }
                        });

                    });



                    // Submit form
                    form.submit();
                });
            }

            // Initialize - if category is pre-selected (from URL parameter)
            @if(isset($selectedCategoryId) && $selectedCategoryId)
            loadProductsForCategory('{{ $selectedCategoryId }}', '{{ $categories->firstWhere("id", $selectedCategoryId)->title ?? "Category" }}', 1, 10, '');
            @endif

            // // Event delegation for dynamically created checkboxes (backup)
            // document.addEventListener('change', function(event) {
            //     if (event.target.classList.contains('product-checkbox')) {
            //         const productId = parseInt(event.target.dataset.productId);
            //         if (productId && typeof toggleProductSelection === 'function') {
            //             toggleProductSelection(productId);
            //         }
            //     }
            // });

            function buildOptionsModal(productId, options) {

                // Remove existing modal if present
                const existing = document.getElementById(`optionsModal${productId}`);
                if (existing) {
                    existing.remove();
                }

                let modalHtml = `
<div class="modal fade"
     id="optionsModal${productId}"
     tabindex="-1"
     role="dialog"
     aria-modal="true"
     data-backdrop="static"
     data-keyboard="false">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-list mr-2"></i>Product Options
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="optionsContainer${productId}">
    `;

                if (!options || options.length === 0) {
                    modalHtml += `<p class="text-muted text-center">No options available</p>`;
                } else {
                    options.forEach((opt, index) => {
                        modalHtml += `
                <div class="form-check mb-2 d-flex align-items-center gap-2">
    <input type="checkbox"
           id="option_${productId}_${index}"
           class="form-check-input option-checkbox"
           data-id="${opt.id}"
           data-index="${index}"
           data-title="${opt.title || ''}"
           data-subtitle="${opt.subtitle || ''}"
           data-default-price="${opt.price || ''}"
           ${opt.is_available ? 'checked' : ''}>

    <label class="form-check-label mr-2" for="option_${productId}_${index}">
        ${opt.title || ''}
        ${opt.subtitle ? ` – ${opt.subtitle}` : ''}
    </label>

   <input type="number"
       class="form-control form-control-sm option-original-price-input"
       style="width:90px;margin-right:5px"
       value="${opt.original_price ?? opt.price ?? ''}"
       placeholder="Original">

<input type="number"
       class="form-control form-control-sm option-price-input"
       style="width:90px"
       value="${opt.price || ''}"
       placeholder="Price">
</div>

            `;
                    });
                }

                modalHtml += `
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveOptions(${productId})">Save</button>
            </div>
        </div>
    </div>
</div>
`;

                // Append and show
                document.body.insertAdjacentHTML('beforeend', modalHtml);


                const modal = $(`#optionsModal${productId}`);
                modal.modal({
                    backdrop: 'static',
                    keyboard: false
                });

                modal.on('hidden.bs.modal', function () {
                    $(this).remove();
                });
            }


            // Open options modal for a product
            window.openOptionsModal = function (productId) {

                const masterProduct = currentProductsData[productId];

                if (!masterProduct) {
                    alert('Product data not found');
                    return;
                }

                // ✅ If already selected → use saved options directly
                if (selectedProducts[productId]?.options?.length) {
                    buildOptionsModal(productId, selectedProducts[productId].options);
                    return;
                }

                let baseOptions = [];
                let savedOptions = [];

// 1️⃣ MASTER OPTIONS (all possible options)
                if (Array.isArray(masterProduct.options)) {
                    baseOptions = masterProduct.options;
                }

// 2️⃣ VENDOR SAVED OPTIONS (only selected ones)
                if (masterProduct.vendor_options) {
                    try {
                        savedOptions = Array.isArray(masterProduct.vendor_options)
                            ? masterProduct.vendor_options
                            : JSON.parse(masterProduct.vendor_options || '[]');
                    } catch (e) {
                        savedOptions = [];
                    }
                }

// 3️⃣ BUILD OPTIONS WITH CORRECT CHECK STATE
                const options = baseOptions.map(opt => {

                    // Try to find vendor saved version
                    const saved = savedOptions.find(saved =>
                        saved.id == opt.id
                    );

                    if (saved) {
                        // 🔥 Use FULL vendor saved object
                        return {
                            ...saved
                        };
                    }

                    // 🔥 Otherwise clone FULL master option
                    return {
                        ...opt,
                        is_available: false
                    };
                });
                buildOptionsModal(productId, options);
                return;



                modal.on('click', function (e) {
                    e.stopPropagation();
                });

            };




            // Open availability modal for a product
            window.openAvailabilityModal = function(productId) {
                const product = selectedProducts[productId];

                let availableDays = product?.available_days || [];
                let availableTimings = product?.available_timings || {};
                const daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

                // Convert old format to new format if needed (backward compatibility)
                if (availableTimings && !Array.isArray(availableTimings) && typeof availableTimings === 'object') {
                    // Check if it's old format: { "Monday": ["09:00-12:00"] }
                    const firstKey = Object.keys(availableTimings)[0];
                    if (firstKey && Array.isArray(availableTimings[firstKey])) {
                        const firstSlot = availableTimings[firstKey][0];
                        if (typeof firstSlot === 'string' && firstSlot.includes('-')) {
                            // Old format - convert to new format
                            const converted = {};
                            Object.keys(availableTimings).forEach(day => {
                                const slots = availableTimings[day];
                                if (Array.isArray(slots)) {
                                    converted[day] = slots.map(slot => {
                                        if (typeof slot === 'string' && slot.includes('-')) {
                                            const parts = slot.split('-');
                                            return { from: parts[0].trim(), to: parts[1]?.trim() || '' };
                                        }
                                        return typeof slot === 'object' ? slot : { from: '', to: '' };
                                    });
                                }
                            });
                            availableTimings = converted;
                        }
                    }
                } else if (Array.isArray(availableTimings)) {
                    // Already in new format - convert to day-based object for modal
                    const converted = {};
                    availableTimings.forEach(item => {
                        if (item.day && item.timeslot) {
                            converted[item.day] = item.timeslot;
                        }
                    });
                    availableTimings = converted;
                }

                // Create modal HTML
                let modalHtml = `
                    <div class="modal fade" id="availabilityModal${productId}" tabindex="-1" role="dialog">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fa fa-calendar mr-2"></i>Available Days & Timings
                                    </h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="font-weight-bold">Select Available Days:</label>
                                           <div class="mb-2">
    <div class="form-check">
        <input type="checkbox"
               class="form-check-input"
               id="select_all_days_${productId}">
        <label class="form-check-label font-weight-bold"
               for="select_all_days_${productId}">
            Select All Days
        </label>
    </div>
</div>
                                        <div class="row mt-2">
                `;

                daysOfWeek.forEach(day => {
                    const checked = availableDays.includes(day) ? 'checked' : '';
                    modalHtml += `
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input day-checkbox-modal" type="checkbox"
                                           id="day_${productId}_${day}"
                                           value="${day}"
                                           ${checked}
                                           data-day="${day}">
                                    <label class="form-check-label" for="day_${productId}_${day}">
                                        ${day}
                                    </label>
                                </div>
                            </div>
                    `;
                });

                modalHtml += `
                                        </div>
                                    </div>
                                    <div id="timings-container-modal${productId}" class="mt-3">
                `;

                daysOfWeek.forEach(day => {
                    const daySlots = availableTimings[day] || [];
                    const isChecked = availableDays.includes(day);
                    modalHtml += `
                        <div class="day-timings-group-modal mb-3" data-day="${day}" style="display: ${isChecked ? 'block' : 'none'};">
                            <label class="font-weight-bold">${day} Time Slots:</label>
                            <div class="timings-list-modal mt-2" data-day="${day}">
                    `;

                    if (daySlots.length > 0) {
                        daySlots.forEach((slot, slotIndex) => {
                            const from = slot.from || (typeof slot === 'string' && slot.includes('-') ? slot.split('-')[0].trim() : '');
                            const to = slot.to || (typeof slot === 'string' && slot.includes('-') ? slot.split('-')[1]?.trim() : '');
                            modalHtml += `
                                <div class="row align-items-end mb-2 timing-row-modal" data-day="${day}" data-index="${slotIndex}">
                                    <div class="col-md-4">
                                        <label class="form-label small">From</label>
                                        <input type="time"
                                               class="form-control form-control-sm time-slot-from-modal"
                                               value="${from}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">To</label>
                                        <input type="time"
                                               class="form-control form-control-sm time-slot-to-modal"
                                               value="${to}">
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-time-slot-modal w-100" title="Remove time slot">
                                            <i class="fa fa-times mr-1"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                    } else if (isChecked) {
                        modalHtml += `
                            <div class="row align-items-end mb-2 timing-row-modal" data-day="${day}" data-index="0">
                                <div class="col-md-4">
                                    <label class="form-label small">From</label>
                                    <input type="time"
                                           class="form-control form-control-sm time-slot-from-modal">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">To</label>
                                    <input type="time"
                                           class="form-control form-control-sm time-slot-to-modal">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-time-slot-modal w-100" title="Remove time slot">
                                        <i class="fa fa-times mr-1"></i> Remove
                                    </button>
                                </div>
                            </div>
                        `;
                    }

                    modalHtml += `
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary add-time-slot-modal" data-day="${day}">
                                <i class="fa fa-plus mr-1"></i> Add Time Slot
                            </button>
                        </div>
                    `;
                });

                modalHtml += `
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" onclick="saveAvailability(${productId})">
                                        <i class="fa fa-save mr-1"></i> Save
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if any
                const existingModal = document.getElementById(`availabilityModal${productId}`);
                if (existingModal) {
                    existingModal.remove();
                }

                // Add modal to body
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                $(`#availabilityModal${productId}`).modal('show');
                setTimeout(() => {
                    $(`#availabilityModal${productId}`)
                        .find('.day-checkbox-modal:checked')
                        .trigger('change');
                }, 50);
                // Auto trigger checked days to show timing slots
                $(`#availabilityModal${productId}`)
                    .find('.day-checkbox-modal:checked')
                    .trigger('change');



                // Handle add time slot
                $(`#availabilityModal${productId}`).on('click', '.add-time-slot-modal', function() {
                    const day = $(this).data('day');
                    addTimeSlotModal(productId, day);
                });

                // Handle remove time slot
                $(`#availabilityModal${productId}`).on('click', '.remove-time-slot-modal', function() {
                    $(this).closest('.timing-row-modal').remove();
                });

                // Clean up modal on close
                $(`#availabilityModal${productId}`).on('hidden.bs.modal', function() {
                    $(this).remove();
                });
            };

            // Add time slot in modal
            window.addTimeSlotModal = function(productId, day) {
                const timingsList = $(`#availabilityModal${productId} .timings-list-modal[data-day="${day}"]`);
                // Get the highest index for this day
                let maxIndex = -1;
                timingsList.find('.timing-row-modal').each(function() {
                    const index = parseInt($(this).data('index') || -1);
                    if (index > maxIndex) maxIndex = index;
                });
                const newIndex = maxIndex + 1;

                const slotHtml = `
                    <div class="row align-items-end mb-2 timing-row-modal" data-day="${day}" data-index="${newIndex}">
                        <div class="col-md-4">
                            <label class="form-label small">From</label>
                            <input type="time"
                                   class="form-control form-control-sm time-slot-from-modal">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">To</label>
                            <input type="time"
                                   class="form-control form-control-sm time-slot-to-modal">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-time-slot-modal w-100" title="Remove time slot">
                                <i class="fa fa-times mr-1"></i> Remove
                            </button>
                        </div>
                    </div>
                `;
                timingsList.append(slotHtml);
                timingsList.find('.timing-row-modal').last().find('.time-slot-from-modal').focus();
            }

            window.saveOptions = function (productId) {

                if (!selectedProducts[productId]) {
                    alert('Please select the product first');
                    return;
                }

                const modal = document.getElementById(`optionsModal${productId}`);
                if (!modal) return;

                const updatedOptions = [];

                modal.querySelectorAll('.option-checkbox:checked').forEach(cb => {

                    const wrapper = cb.closest('.form-check');

                    const originalPriceInput = wrapper.querySelector('.option-original-price-input');
                    const priceInput = wrapper.querySelector('.option-price-input');

                    // First try to get already edited vendor option
                    let baseOption = selectedProducts[productId]?.options?.find(
                        o => o.id == cb.dataset.id
                    );

// If not found, fallback to master option
                    if (!baseOption) {
                        baseOption = currentProductsData[productId]?.options?.find(
                            o => o.id == cb.dataset.id
                        );
                    }

                    if (baseOption) {

                        // Clone full structure (vendor or master)
                        const optionCopy = JSON.parse(JSON.stringify(baseOption));

                        // Override editable fields
                        optionCopy.original_price =
                            originalPriceInput && originalPriceInput.value !== ''
                                ? originalPriceInput.value
                                : optionCopy.original_price ?? optionCopy.price ?? 0;

                        optionCopy.price =
                            priceInput && priceInput.value !== ''
                                ? priceInput.value
                                : optionCopy.price ?? 0;

                        optionCopy.is_available = true;

                        updatedOptions.push(optionCopy);
                    }
                });

                if (updatedOptions.length > 0) {
                    selectedProducts[productId].options = updatedOptions;
                } else {
                    selectedProducts[productId].options = [];
                }

                $(`#optionsModal${productId}`).modal('hide');
            };





            // Save availability for a product
            window.saveAvailability = function(productId) {
                if (!selectedProducts[productId]) {
                    alert('Product not selected. Please select the product first.');
                    return;
                }

                const modal = $(`#availabilityModal${productId}`);
                const availableDays = [];
                const availableTimings = {}; // Store in day-based format for modal/display, will be converted to new format on submit

                // Get selected days
                modal.find('.day-checkbox-modal:checked').each(function() {
                    const day = $(this).data('day');
                    availableDays.push(day);

                    // Get time slots for this day (new format: separate from/to fields)
                    const slots = [];
                    modal.find(`.timings-list-modal[data-day="${day}"] .timing-row-modal`).each(function() {
                        const from = $(this).find('.time-slot-from-modal').val().trim();
                        const to = $(this).find('.time-slot-to-modal').val().trim();
                        if (from && to) {
                            slots.push({ from: from, to: to });
                        }
                    });

                    if (slots.length > 0) {
                        availableTimings[day] = slots;
                    }
                });

                // Update product data
                selectedProducts[productId].available_days = availableDays;
                selectedProducts[productId].available_timings = availableTimings; // Store in day-based format, will be converted on submit

                // Update display
                const daysCell = document.querySelector(`.availability-days-cell[data-product-id="${productId}"]`);
                const timingsCell = document.querySelector(`.availability-timings-cell[data-product-id="${productId}"]`);

                if (daysCell) {
                    daysCell.innerHTML = renderAvailabilityDays(productId, availableDays);
                }
                if (timingsCell) {
                    timingsCell.innerHTML = renderAvailabilityTimings(productId, availableTimings);
                }

                // Close modal
                modal.modal('hide');
            };
        });
        function attachAddonEventHandlers() {
            // no-op safety function
            // keeps old calls from breaking the page
        }

        // SELECT ALL DAYS
        $(document).on('change', '[id^="select_all_days_"]', function () {

            const productId = $(this).attr('id').replace('select_all_days_', '');
            const checked = $(this).is(':checked');

            const modal = $(`#availabilityModal${productId}`);

            modal.find('.day-checkbox-modal')
                .prop('checked', checked)
                .trigger('change');
        });


        // INDIVIDUAL DAY CHECKBOX
        $(document).on('change', '.day-checkbox-modal', function () {

            const modal = $(this).closest('.modal');
            const day = $(this).data('day');
            const isChecked = $(this).is(':checked');

            // Show or hide the timing section
            modal.find(`.day-timings-group-modal[data-day="${day}"]`)
                .toggle(isChecked);

            // Add first time slot automatically
            if (isChecked) {
                const timingsList = modal.find(`.timings-list-modal[data-day="${day}"]`);

                if (timingsList.children().length === 0) {
                    const productId = modal.attr('id').replace('availabilityModal','');
                    addTimeSlotModal(productId, day);
                }
            }

            // Update "Select All Days" checkbox
            const totalDays = modal.find('.day-checkbox-modal').length;
            const selectedDays = modal.find('.day-checkbox-modal:checked').length;

            modal.find('[id^="select_all_days_"]').prop('checked', totalDays === selectedDays);

        });
    </script>

    <style>
        .category-card {
            cursor: pointer;
        }

        .category-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
        }

        .product-row {
            transition: all 0.2s ease;
        }

        .product-row:hover {
            background-color: #f8f9fa !important;
        }

        .product-row.table-primary {
            background-color: #cfe2ff !important;
        }

        .product-row.table-success {
            background-color: #d1e7dd !important;
        }

        .product-row.table-success.table-primary {
            background-color: #cfe2ff !important;
            border: 2px solid #0d6efd !important;

        }

        .addon-row {
            display: flex;
            align-items: center;
        }

        #products-table {
            font-size: 0.9rem;
        }

        #products-table th {
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        #products-table td {
            vertical-align: middle;
        }

        /* Ensure checkboxes are visible */
        .form-check-input {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
            width: 18px !important;
            height: 18px !important;
            margin: 0 auto !important;
            cursor: pointer !important;
            -webkit-appearance: checkbox !important;
            -moz-appearance: checkbox !important;
            appearance: checkbox !important;
        }

        .form-check-input:disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }

        /* Ensure save button is visible */
        #selected-products-summary {
            display: block !important;
            background-color: #f8f9fa !important;
            border: 2px solid #dee2e6 !important;
        }

        #save-selected-products {
            display: inline-block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        /* CRITICAL: Override global CSS that hides checkboxes */
        /* Global CSS has [type="checkbox"] { position: absolute; left: -9999px; opacity: 0; } */
        /* We need to override this for our table checkboxes */

        #products-table input[type="checkbox"],
        #products-table .form-check-input,
        #products-table .product-checkbox,
        #products-table .product-publish,
        #products-table .product-available,
        #select-all-products {
            position: relative !important;
            left: auto !important;
            top: auto !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        /* Category Autocomplete Styles */
        .category-autocomplete-wrapper {
            position: relative;
        }

        .category-autocomplete-dropdown {
            font-size: 0.95rem;
        }

        .category-autocomplete-item:hover {
            background-color: #f8f9fa !important;
        }

        .category-autocomplete-item:active {
            background-color: #e9ecef !important;
        }


        /* Override for checked/unchecked states */
        #products-table input[type="checkbox"]:checked,
        #products-table input[type="checkbox"]:not(:checked),
        #products-table .form-check-input:checked,
        #products-table .form-check-input:not(:checked) {
            position: relative !important;
            left: auto !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        /* Ensure checkbox column is visible */
        #products-table th:first-child,
        #products-table td:first-child {
            min-width: 60px !important;
            width: 60px !important;
            padding: 8px !important;
            text-align: center !important;
            position: relative !important;
        }

        /* Ensure Published and Available columns show checkboxes */
        #products-table td:nth-last-child(2),
        #products-table td:last-child {
            text-align: center !important;
            padding: 8px !important;
            position: relative !important;
        }

        /* Select all checkbox in header */
        #select-all-products {
            position: relative !important;
            left: auto !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
    </style>
@endsection
