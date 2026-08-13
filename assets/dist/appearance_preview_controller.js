import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        baseUrl: String,
        inheritedLayers: Object,
    };

    static targets = ['background', 'face', 'uniform', 'hair', 'amulet'];

    connect() {
        this.update = this.update.bind(this);
        this.element.addEventListener('change', this.update);
        this.update();
    }

    disconnect() {
        this.element.removeEventListener('change', this.update);
    }

    update() {
        const folders = {
            background: 'backgrounds',
            face: 'faces',
            uniform: 'uniforms',
            hair: 'hair',
            amulet: 'amulets',
        };

        Object.entries(folders).forEach(([category, folder]) => {
            const select = this.element.querySelector(`select[name$="[${category}]"]`);
            const target = this[`${category}Target`];
            if (!select || !target) {
                return;
            }

            const filename = select.value || this.inheritedLayersValue[category] || '';
            if (!filename) {
                target.style.display = 'none';
                target.removeAttribute('src');
                return;
            }

            target.src = `${this.baseUrlValue}${folder}/${encodeURIComponent(filename)}`;
            target.style.display = 'block';
        });
    }
}
