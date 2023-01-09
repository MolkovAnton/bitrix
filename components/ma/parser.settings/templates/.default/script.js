if(typeof(BX.ParserSettings) === "undefined")
{
    BX.ParserSettings = function(){
        /* 
         * Инициализация данных. Можно по умолчанию ставить пустые
         * а затем назначать реальные данные в методе initialize
         */
        this.settings = null;
        this.componentName = null;
    };
    
    BX.ParserSettings.prototype = 
    {
        //Первая инициализация
        initialize: function(settings)
        {
            /*
             * Метод инициализации, получет данные из template через свойство settings
             */
            this.settings = settings.settings;
            this.componentName = settings.componentName;
            
            //Обработчики событий
            for (var id in this.settings) {
                BX('set_search_words_button_'+id).addEventListener('click', this.onButtonClick);
                BX('search_words_'+id).addEventListener('change', function(e){
                    BX.ParserSettings.settings[e.target.dataset['id']] = e.target.value;
                });
            }
        },
        
        onButtonClick: function(e)
        {
            var id = e.target.dataset['id'];
            BX.ajax.runComponentAction(BX.ParserSettings.componentName, 'setSettings', {
                mode: 'class',
                data: {
                    post: {
                        id: id,
                        req_words: BX.ParserSettings.settings[id],
                    }
                },
            })
            .then(function(response) {
                console.log(response);
            });
        },   
    };
    
    //Инициализирует синглтон
    BX.ParserSettings.create = function(settings)
    {
        var self = new BX.ParserSettings();
        self.initialize(settings);
        return self;
    };
}
