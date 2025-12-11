<script>
document.addEventListener('DOMContentLoaded', function () {
    const addOnTemplate = document.getElementById('add-on-template');
    const specTemplate = document.getElementById('spec-template');

    function appendClone(template, containerSelector) {
        const container = document.querySelector(containerSelector);
        if (!container || !template) {
            return;
        }

        const clone = template.content.cloneNode(true);
        container.appendChild(clone);
    }

    document.querySelectorAll('[data-addons-add]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            appendClone(addOnTemplate, '[data-addons-container]');
        });
    });

    document.querySelectorAll('[data-specs-add]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            appendClone(specTemplate, '[data-specs-container]');
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.matches('[data-remove-row]')) {
            event.preventDefault();
            const row = event.target.closest('.repeatable-row');
            if (row) {
                row.remove();
            }
        }
    });
});
$(document).ready(function() {

    // 1. Filter dropdown options based on search
    $('#food_category_search').on('keyup', function() {
        var search = $(this).val().toLowerCase();
        $('#food_category option').each(function() {
            if ($(this).val() === "") return; // skip placeholder
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(search) > -1);
        });
    });

    // 2. When selecting from dropdown, update tags
    $('#food_category').on('change', function() {
        updateSelectedFoodCategoryTags();
    });

    // 3. Remove tag and unselect dropdown
    $('#selected_categories').on('click', '.remove-tag', function() {
        var value = $(this).closest('.selected-category-tag').data('value');
        $('#food_category option[value="' + value + '"]').prop('selected', false);
        updateSelectedFoodCategoryTags();
    });

    // 4. Show tags on page load (important for edit form)
    updateSelectedFoodCategoryTags();
});

// 5. Tag generation
function updateSelectedFoodCategoryTags() {
    var html = '';
    $('#food_category option:selected').each(function() {
        if ($(this).val() !== "") {
            html += `
                <span class="selected-category-tag" data-value="${$(this).val()}">
                    ${$(this).text()}
                    <span class="remove-tag">&times;</span>
                </span>`;
        }
    });
    $('#selected_categories').html(html);
}
</script>

