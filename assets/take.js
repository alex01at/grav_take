document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('take-add-item')) {
        return;
    }
    e.preventDefault();

    var container = e.target.previousElementSibling;
    if (!container || !container.classList.contains('take-items-fields')) {
        container = e.target.closest('form').querySelector('.take-items-fields');
    }
    if (!container) {
        return;
    }

    var firstLabel = container.querySelector('label');
    var clone = firstLabel.cloneNode(true);
    clone.querySelector('input').value = '';
    container.appendChild(clone);
});
