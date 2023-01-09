class Segments {
    events = [];
    
    constructor(settings) {
        this.settings = settings;
        this.$container = BX(settings.container);
        this.registerListeners(this.$container);
        BX.addCustomEvent('BX.Main.Grid:onBeforeReload', BX.proxy(this.deleteSelectedElements, this));
    }
    getTemplate(template, event) {
        event.preventDefault();
        BX.ajax.runComponentAction(this.settings.componentName, 'getView', {
            mode: 'class',
            data: {
                template: this.settings.templatePath+'view/'+template
            }
        })
        .then(response => {
            this.unregisterListeners();
            this.$container.innerHTML = response.data;
            this.registerListeners(this.$container);
            if (this.sendGuidsButton) {
                delete(this.sendGuidsButton);
            }
        });
    }
    registerListeners(container) {
        const $domEvents = container.querySelectorAll('[data-events]');
        $domEvents.forEach($el => {
            const events = $el.dataset.events.split(';');
            events.forEach(event => {
                const eventArr = event.split('|');
                const eventType = eventArr[0];
                const args = eventArr[2] ? JSON.parse(eventArr[2]) : null;
                if (this[eventArr[1]]) {
                    const eventFunc = this[eventArr[1]].bind(this, args);
                    $el.addEventListener(eventType, eventFunc);
                    this.events.push({
                        element: $el,
                        event: eventType,
                        func: eventFunc
                    });
                }
            });
        });
    }
    unregisterListeners() {
        this.events.forEach(listener => {
            listener.element.removeEventListener(listener.type, listener.callback);
        });
    }
    uploadGuids(_, event) {
        event.preventDefault();
        const guids = event.target.closest('form').querySelector('[data-name="guid"]').value.split('\n');
        if (!guids) {
            return;
        }
        BX.ajax.runComponentAction(this.settings.componentName, 'partnersSearch', {
            mode: 'class',
            data: {
                data: {
                    guids: guids,
                    blockCode: this.settings.params.PARTNERS_IBLOCK_CODE,
                    template: this.settings.templatePath+'view/'
                }
            },
        })
        .then(response => {
            this.searchResult = response.data.result;
            const $result = document.createElement('div');
            const $resultCont = this.$container.querySelector('#search_result');
            $result.innerHTML = response.data.html;
            this.registerListeners($result);
            $resultCont.innerHTML = '';
            $resultCont.append($result);
        });
    }
    checkInput(_, event) {
        const target = event.target;
        if (!this.sendGuidsButton) {
            this.sendGuidsButton = event.target.closest('form').querySelector('[data-name="uploadGuidsButton"]');
        }
        if (!target.value && !this.sendGuidsButton.disabled) {
            this.sendGuidsButton.disabled = true;
        } else if (target.value && this.sendGuidsButton.disabled) {
            this.sendGuidsButton.disabled = false;
        }
    }
    deleteDuplicate(params, event) {
        const $elemen = event.target;
        const $parent = $elemen.closest('[data-name="partner"]');
        this.searchResult[params.guid].SUB_RESULT.splice(params.key, 1);
        $elemen.closest('[data-name="partnerName"]').remove();
        if (this.searchResult[params.guid].SUB_RESULT.length === 1) {
            this.searchResult[params.guid] = this.searchResult[params.guid].SUB_RESULT.pop();
            $parent.classList.remove('error');
            $parent.querySelector('[data-name="partnerRemove"]').remove();
        }
    }
    addSegment(_, event) {
        event.preventDefault();
        const segmentName = event.target.closest('form').querySelector('input').value;
        this.checkResult();
        
        BX.ajax.runComponentAction(this.settings.componentName, 'addSegment', {
            mode: 'class',
            data: {
                params: {
                    name: segmentName,
                    elements: this.searchResult,
                    params: this.settings.params,
                    template: this.settings.templatePath+'view/',
                    curUrl: this.settings.curUrl
                }
            }
        })
        .then(response => {
            this.unregisterListeners();
            this.$container.innerHTML = response.data;
            this.registerListeners(this.$container);
        });
    }
    checkResult() {
        Object.keys(this.searchResult).forEach(key => {
            if (this.searchResult[key].SUB_RESULT) {
                delete(this.searchResult[key]);
            }
        });
    }
    deleteSegment(id, action = 'deleteSegment') {
        BX.ajax.runComponentAction(this.settings.componentName, action, {
            mode: 'class',
            data: {
                data: {
                    id: id,
                    params: this.settings.params
                }
            }
        })
        .then(response => {
            if (response.data === true) {
                BX.Main.gridManager.reload(this.settings.gridId);
            }
        });
    }
    deleteSelectedElements(grid) {
        const ids = grid.getRows().getSelectedIds();
        this.deleteSegment(ids, 'deleteSegmentElement');
    }
}
