document.addEventListener('DOMContentLoaded', () => {
    BX.Vue3.BitrixVue.createApp({
        data: () => {
            return {
                currentDep: 'all',
                openedVacancy: {}
            }
        },
        methods: {
            showDep(e){
                this.currentDep = e.target.dataset.department;
            },
            showDepVacancies(dep){
                if (this.currentDep === 'all' || this.currentDep === dep) {
                    return true;
                }
                return false;
            },
            showVacancy(id){
                this.openedVacancy[id] = this.openedVacancy[id] ? false : true;
                this.$refs[id].style.maxHeight = this.openedVacancy[id] ? this.$refs[id].scrollHeight + 'px' : '';
            },
            getForm(url){
                BX.ajax.get(url,
                    data => {
                        let popup = BX.PopupWindowManager.create('vacancy-form', null, {
                            autoHide : false,
                            offsetTop : 1,
                            offsetLeft : 0,
                            lightShadow : true,
                            closeIcon : true,
                            closeByEsc : true,
                            overlay: {
                                backgroundColor: '#FFFFFF', opacity: '60'
                            }
                        });
                        BX.addCustomEvent('onAjaxSuccess', e=>popup.handleResizeWindow());
                        popup.setContent(data);
                        popup.show();
                    }
                );
            }
        },
    }).mount('.vacancies');
});
