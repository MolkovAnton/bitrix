if(typeof(BX.JiraFormUniversal) === "undefined")
{
    BX.JiraFormUniversal = function(){
        /* 
         * Инициализация данных. Можно по умолчанию ставить пустые
         * а затем назначать реальные данные в методе initialize
         */
        this.form = null;
        this.componentName = ''; //Имя компонента
        this.container = null;
        this.options = null;
        this.requestOptions = null;
        this.usersFields = null;
        this.alias = null;
    };
    
    BX.JiraFormUniversal.prototype = 
    {
        //Первая инициализация
        initialize: function(settings)
        {
            /*
             * Метод инициализации, получет данные из template через свойство settings
             */
            this.componentName = settings.componentName;
            this.container = BX(settings.container);
            this.form = BX(settings.formId);
            this.options = settings.options;
            this.requestOptions = settings.requestOptions;
            this.usersFields = settings.usersFields;
            this.alias = settings.alias;

            //Обработчики событий
            this.form.querySelector('button').addEventListener('click', BX.delegate(this.onSendButtonClick, this));
            
            //Подключение событий из методов ядра
            //BX.addCustomEvent('BX.Main.User.SelectorController:select', BX.proxy(this.setSomeMethod, this));
        },
        
        onSendButtonClick: function(e)
        {
            e.preventDefault();
            if (!this.validateForm(this.form)) {
                return;
            }
            
            var data = this.serializeForm(this.form);
            var self = this;
            BX.ajax.runComponentAction(this.componentName, 'sendFormToJira', {
                mode: 'class',
                data: {
                    post: {
                        DATA: {
                            FIELDS: data,
                            OPTIONS: this.options,
                            REQUEST_OPTIONS: this.requestOptions,
                            USERS_FIELDS: this.usersFields,
                            ALIAS: this.alias
                        }, 
                    }
                },
            })
            .then(function(response) {
                console.log(response);
                if (typeof response.data.errors !== "undefined" && Object.keys(response.data.errors).length > 0) {
                    var errors = [];
                    for (var i in response.data.errors) {
                        var error = BX.create('DIV', {
                            attrs: {className: 'jira-error'},
                            text: response.data.errors[i]
                        });
                        errors.push(error);
                    }
                    var errorsCont = BX.create('DIV', {
                        attrs: {className: 'jira-errors-container'},
                        children: errors
                    });
                    self.container.prepend(errorsCont);
                } else {
                    let message = typeof response.data._links.web !== 'undefined' ? 'Задача отправлена в Jira, <a href = "'+response.data._links.web+'">ссылка</a>' : 'Задача отправлена в Jira';
                    self.container.innerHTML = "<div class='jira-response'>"+message+"</div>";
                }
            });
        },
        
        validateForm: function(form)
        {
            var valid = true;
            
            for (var i = 0; i < form.elements.length; i++) {
                var field = form.elements[i];
                if (field.required && field.value === '') {
                    valid = false;
                    field.classList.add('error');
                    field.placeholder = "Обязательное";
                }
            }
            
            return valid;
        },
        
        serializeForm: function (form) {

            // Setup our serialized data
            var serialized = {};

            // Loop through each field in the form
            for (var i = 0; i < form.elements.length; i++) {
                let field = form.elements[i];
                // Don't serialize fields without a name, submits, buttons, file and reset inputs, and disabled fields
                if (!field.name || field.disabled || field.type === 'reset' || field.type === 'file' || field.type === 'submit' || field.type === 'button') continue;
                
                if (field.nodeName === 'SELECT' && field.multiple) {
                    let optArray = [];
                    for (var i=0, iLen=field.options.length; i<iLen; i++) {
                        let opt = field.options[i];
                        if (opt.selected) {
                            optArray.push({value: opt.value});
                        }
                    }
                    serialized[field.name] = optArray;
                } else {
                    if (typeof field.dataset.alias !== 'undefined' && field.dataset.alias !== '') {
                        let alias = field.dataset.alias;
                        serialized[field.name] = {};
                        serialized[field.name][alias] = field.value;
                    } else {
                        serialized[field.name] = field.value;
                    }
                }
            }
            return serialized;
        }   
    };
    
    //Инициализирует синглтон
    BX.JiraFormUniversal.create = function(settings)
    {
        var self = new BX.JiraFormUniversal();
        self.initialize(settings);
        return self;
    };
}
