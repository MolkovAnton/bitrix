class CustomComponent {
    events = [];
    
    constructor(settings) {
        this.settings = settings;
        this.$container = BX(settings.container);
        this.registerListeners(this.$container);
    }
    registerListeners(container) {
        const $domEvents = container.querySelectorAll('[data-events]');
        $domEvents.forEach($el => {
            const eventsArr = JSON.parse($el.dataset.events);
            if (Array.isArray(eventsArr) && eventsArr.length > 0) {
                eventsArr.forEach(event => {
                    const eventType = event.event;
                    const args = event.params ? event.params : null;
                    if (this[event.function]) {
                        const eventFunc = this[event.function].bind(this, args);
                        $el.addEventListener(eventType, eventFunc);
                        this.events.push({
                            element: $el,
                            event: eventType,
                            func: eventFunc
                        });
                    }
                });
            }
        });
    }
    unregisterListeners() {
        this.events.forEach(listener => {
            listener.element.removeEventListener(listener.type, listener.callback);
        });
    }
    getView(action, params, container = this.$container) {
        BX.ajax.runComponentAction(this.settings.componentName, action, {
            mode: 'class',
            data: {
                template: this.settings.tempalte,
                params
            }
        })
        .then(response => {
            this.unregisterListeners();
            container.innerHTML = response.data.html;
            this.registerListeners(this.$container);
        });
    }
}