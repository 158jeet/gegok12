const mix = require("laravel-mix");
const path = require("path");

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 | Tailwind 3 runs via PostCSS (postcss.config.js); no Mix Tailwind/PurgeCSS.
 */

// Resolve 'vue' to a default-export shim for Vue 2 packages that use "import Vue from 'vue'"
mix.webpackConfig({
    plugins: [
        new (class VueCompatPlugin {
            apply(compiler) {
                compiler.hooks.normalModuleFactory.tap("VueCompatPlugin", (nmf) => {
                    nmf.hooks.beforeResolve.tap("VueCompatPlugin", (resolveData) => {
                        if (
                            resolveData.request === "vue" &&
                            resolveData.contextInfo.issuer &&
                            (resolveData.contextInfo.issuer.includes("vue-image-lightbox-carousel") ||
                                (resolveData.contextInfo.issuer.includes("@fullcalendar/vue") &&
                                    !resolveData.contextInfo.issuer.includes("@fullcalendar/vue3")))
                        ) {
                            resolveData.request = path.resolve(
                                __dirname,
                                "resources/assets/js/vue-default-shim.js"
                            );
                        }
                    });
                });
            }
        })(),
    ],
});

mix.js("resources/assets/js/app.js", "public/js")
    .vue({ version: 3 })
    .extract()
    .sass("resources/assets/sass/app.scss", "public/css");

if (mix.inProduction()) {
    mix.version();
} else {
    mix.sourceMaps();
}
