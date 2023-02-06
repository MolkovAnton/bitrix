class NewComponent extends CustomComponent {
    buttonClick(param) {
        this.getView('getContent', param);
    }
    buttonHover(param) {
        console.log(param);
    }
}