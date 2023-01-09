if(typeof(BX.InvestProjectsImport) === "undefined")
{
    BX.InvestProjectsImport = function(){
        /* 
         * Инициализация данных. Можно по умолчанию ставить пустые
         * а затем назначать реальные данные в методе initialize
         */
        this.someData = null;
        this.container = null;
        this.textarea = null;
        this.button = null;
        this.ids = [];
        this.inputResults = null;
        this.additional = null;
        this.messages = {
            Add: 'Добавлен',
            Update: 'Обновлен'
        }, 
        this.progressBar = null;
    };
    
    BX.InvestProjectsImport.prototype = 
    {
        //Первая инициализация
        initialize: function(settings)
        {
            /*
             * Метод инициализации, получет данные из template через свойство settings
             */
            this.componentName = settings.componentName;
            this.container = BX(settings.container);
            this.textarea = this.container.querySelector('textarea');
            this.button = this.container.querySelector('.ui-btn-main');
            this.inputResults = BX(settings.inputResults);
            this.additional = this.container.querySelector('input[name="additional"]');
           
            //Обработчики событий
            this.button.addEventListener('click', BX.delegate(this.onButtonClick, this));
            this.textarea.addEventListener('change', BX.delegate(this.checkInput, this));
        },
        
        //Пример ajax запроса к методу компонента
        sendProjectData: function(projects)
        {
            let self = this;
            return BX.ajax.runComponentAction(this.componentName, 'addProjects', {
                mode: 'class',
                data: {
                    post: {
                        PROJECTS: projects,
                        ADDITIONAL: self.additional.checked
                    }
                },
            }).then(function(response) {
                if (typeof response.data !== 'undefined') {
                    for (let id in response.data) {
                        let result;
                        if (typeof response.data[id].ERROR !== 'undefined') {
                            result = BX.create('DIV', {
                                attrs: {className: 'error'},
                                text: id + " - Ошибка. " + response.data[id].ERROR
                            });
                            self.inputResults.append(result);
                        } else {
                            result = BX.create('DIV', {
                                attrs: {className: 'succes'},
                                text: id + " - " + self.messages[response.data[id]]
                            });
                            self.inputResults.append(result);
                        }
                    }
                }
                self.progressBar.update(self.progressBar.getValue() + projects.length);
            });
        },
        
        onButtonClick: function()
        {
            if (this.ids === null || this.ids.length === 0) {
                alert('Не задан ни один id');
            } else {
                this.addProjects();
            }
        },
        
        checkInput: function(e)
        {
            this.ids = e.target.value.match(/[0-9]+/g);
            e.target.value = this.ids.join('|');
        },
        
        addProjects: function()
        {
            this.button.classList.add('hidden');
            this.progressBar = new BX.UI.ProgressBar({
                maxValue: this.ids.length,
                statusType: 'COUNTER',
                color: 'PRIMARY',
                
            });
            this.inputResults.append(this.progressBar.getContainer());
            this.ids.forEach(el => {
                this.sendProjectData([el]);
            });
        }
    };
    
    //Инициализирует синглтон
    BX.InvestProjectsImport.create = function(settings)
    {
        var self = new BX.InvestProjectsImport();
        self.initialize(settings);
        return self;
    };
}
