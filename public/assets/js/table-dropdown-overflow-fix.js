/**
 * Bootstrap's `.table-responsive` sets `overflow-x: auto` and leaves
 * `overflow-y` unset — but per the CSS Overflow spec, if either axis is
 * non-visible, the *other* axis is also forced to `auto`. That silently
 * clips any per-row Actions dropdown that extends below the table's own
 * height (most visible on short tables, where a dropdown near the bottom
 * row gets cut off or shows a phantom gap between its items).
 *
 * Fix: temporarily set the table's scroll container to `overflow: visible`
 * only while one of its dropdowns is open, restoring it on close — this
 * keeps horizontal scrolling intact for normal table browsing.
 */
(function () {
    document.addEventListener('show.bs.dropdown', function (event) {
        var scrollParent = event.target.closest('.table-responsive');
        if (scrollParent) {
            scrollParent.dataset.prevOverflow = scrollParent.style.overflow;
            scrollParent.style.overflow = 'visible';
        }
    });

    document.addEventListener('hide.bs.dropdown', function (event) {
        var scrollParent = event.target.closest('.table-responsive');
        if (scrollParent) {
            scrollParent.style.overflow = scrollParent.dataset.prevOverflow || '';
        }
    });
})();
