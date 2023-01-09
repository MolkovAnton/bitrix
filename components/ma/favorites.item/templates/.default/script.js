if (typeof handleFavorite !== "function") { 
    function handleFavorite(type, id, element) {
        BX.ajax.runComponentAction('MA:favorites.item', 'handleFavorite', {
            mode: 'class',
            data: {
                data: {
                    type: type,
                    id: id
                }
            },
        })
        .then(response => {
            if (response.errors.length === 0) {
                element.classList.toggle('is-active');
            }
        });
    }
}