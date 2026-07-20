import { createApp, h, type Component, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import {
    create,
    NAlert,
    NButton,
    NCard,
    NConfigProvider,
    NDataTable,
    NEmpty,
    NFormItem,
    NInput,
    NInputNumber,
    NPopconfirm,
    NSelect,
    NSpace,
    NSwitch,
    NTag,
    darkTheme,
} from 'naive-ui';
import AppLayout from './Layouts/AppLayout.vue';

const naive = create({
    components: [
        NAlert,
        NButton,
        NCard,
        NConfigProvider,
        NDataTable,
        NEmpty,
        NFormItem,
        NInput,
        NInputNumber,
        NPopconfirm,
        NSelect,
        NSpace,
        NSwitch,
        NTag,
    ],
});

interface PageModule {
    default: Component & { layout?: Component };
}

void createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob<PageModule>('./Pages/**/*.vue', { eager: true });
        const page = pages[`./Pages/${name}.vue`];
        if (!page) {
            throw new Error(`Unknown Inertia page: ${name}`);
        }

        if (page.default.layout === undefined) {
            page.default.layout = AppLayout;
        }

        return page.default as DefineComponent;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(naive)
            .mount(el);
    },
});

export { darkTheme };
