import './bootstrap';

import Alpine from 'alpinejs';
import megaMenu from './mega-menu';

window.Alpine = Alpine;

Alpine.data('megaMenu', megaMenu);

Alpine.start();
