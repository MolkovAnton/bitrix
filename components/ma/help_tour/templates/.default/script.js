if(typeof(BX.HelpTour) === "undefined")
{
    BX.HelpTour = function(){
        /* 
         * Инициализация данных. Можно по умолчанию ставить пустые
         * а затем назначать реальные данные в методе initialize
         */
        this.componentName = ''; //Имя компонента
        this.tourManager = null;
        this.startPopup = null;
        this.messages = null;
        this.componentParams = null;
        this.stepsIds = [];
        this.newTourButton = null;
        this.autoStart = false;
    };
    
    BX.HelpTour.prototype = 
    {
        //Первая инициализация
        initialize: function(settings)
        {
            /*
             * Метод инициализации, получет данные из template через свойство settings
             */
            this.componentName = settings.componentName;
            this.componentParams = settings.componentParams;
            this.messages = settings.messages;
            this.newTourButton = BX(settings.newTourButton);
            this.tourManager = this.createManager(settings.steps);
            this.allStepsId = settings.steps.map(e => e.target);
            this.autoStart = settings.autoStart;

            if (this.stepsIds.length > 0) {
                this.newTourButton.hidden = false;
                this.newTourButton.addEventListener('click', BX.delegate(this.onStartNewTour, this));
            }

            if (this.tourManager === null) return;
            if (this.autoStart) {
                this.tourManager.start();
            } else {
                this.startPopup = this.createStartPopup();
                this.startPopup.show();
            }
        },
        
        createManager: function(steps) {
            let stepsIds = [];
            let managerSteps = [];
            if (Array.isArray(steps)) {
                steps.forEach(step => {
                    let target = BX(step.target);
                    if (typeof target !== 'undefined' && target !== null) {
                        stepsIds.push(step.target);
                        if (step.shown === 'N') {
                            let newStep = {
                                id: step.target,
                                text: step.text,
                                areaPadding: step.areaPadding || 10,
                                title: step.title,
                                target: target,
                                rounded: step.rounded || false,
                                link: step.link || "",
                                events: {
                                    onShow: BX.delegate(this.setStepPosition, this)
                                }
                            };
                            managerSteps.push(newStep);
                        }
                    }
                });
            } else {
                return null;
            }
            
            this.stepsIds = stepsIds;
            if (managerSteps.length > 0) {
                let manager = BX.UI.Tour.Manager.create({
                    id: 'helpTour',
                    steps: managerSteps,
                    clickOnBackBtn: true
                });
                manager.subscribe("UI.Tour.Guide:onFinish", () => {
                    this.handleTourFinish(this.stepsIds);
                });

                return manager;
            } else {
                return null;
            }
        },
        
        handleTourFinish: function(steps)
        {
            BX.ajax.runComponentAction(this.componentName, 'finishTour', {
                mode: 'class',
                data: {
                    post: {
                        STEPS: steps,
                        COMPONENT_PARAMS: this.componentParams,
                    }
                },
            })
            .then(function(response) {
                console.dir(response);
            });
        },
        
        setStepPosition: function()
        {
            let step = this.tourManager.getCurrentStep();
            let target = step.target.getBoundingClientRect();
            if (target.height > target.width) {
                step.position = "right";
            }
        },
        
        createStartPopup: function()
        {
            let self = this;
            let popup = BX.PopupWindowManager.create("help_tour_start", null, {
                content:
                    BX.create('DIV', {
                        attrs: {className: 'help-popup-start'},
                        children: [
                            BX.create('DIV', {
                                attrs: {className: 'ui-tour-popup-title'},
                                text: this.messages.startPopupTitle
                            }),
                            BX.create('DIV', {
                                attrs: {className: 'ui-tour-popup-text'},
                                text: this.messages.startPopupText
                            }),
                            BX.create('DIV', {
                                attrs: {className: "ui-btns-wrapper"},
                                children: [
                                    BX.create('DIV', {
                                        text: this.messages.closePopupClose,
                                        attrs: {className: "ui-btn-close log-uiClose"},
                                        events: {click: function(e){
                                            popup.destroy();
                                            self.handleTourFinish(self.allStepsId);
                                        }}
                                    }),
                                    BX.create('DIV', {
                                        text: this.messages.startPopupButton,
                                        attrs: {className: "ui-btn-main log-uiStart"},
                                        events: {click: function(e){
                                            self.tourManager.start();
                                            popup.destroy();
                                        }}
                                    }),
                                ],
                            }),

                        ]
                    }),
                autoHide : false,
                offsetTop : 1,
                offsetLeft : 0,
                lightShadow : true,
                closeIcon : false,
                closeByEsc : false,
                overlay: {
                   backgroundColor: '#5F7C93', opacity: '50'
                }
            });
            return popup;
        },
        
        onStartNewTour: function()
        {
            let self = this;
            BX.ajax.runComponentAction(this.componentName, 'getNewTour', {
                mode: 'class',
                data: {
                    post: {
                        STEPS: this.stepsIds,
                        COMPONENT_PARAMS: this.componentParams
                    }
                }
            })
            .then(function(response) {
                if (Array.isArray(response.data) && response.data.length > 0) {
                    let steps = response.data;
                    self.tourManager = self.createManager(steps);
                    if (typeof self.tourManager === 'object' && self.tourManager !== null) {
                        self.tourManager.start();
                    }
                }
                if (Array.isArray(response.errors) && response.errors.length > 0) {
                    console.dir(response.errors);
                }
            });
        }
    };
    
    //Инициализирует синглтон
    BX.HelpTour.create = function(settings)
    {
        var self = new BX.HelpTour();
        self.initialize(settings);
        return self;
    };
}
