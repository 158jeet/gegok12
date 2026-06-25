/**
 * Shim for Vue 2 packages (e.g. vue-image-lightbox-carousel) that do "import Vue from 'vue'".
 * Vue 3 has no default export; this provides a compat default and re-exports all Vue 3 APIs
 * so both "import Vue from 'vue'" and "import { createElementVNode } from 'vue'" work.
 */
import * as Vue from "vue";

const compat = { ...Vue };
compat.use = () => {};
compat.component = () => {};
compat.directive = () => {};
compat.mixin = () => {};
compat.prototype = {};
compat.config = {};
compat.util = {};
compat.set = () => {};
compat.delete = () => {};
compat.nextTick = Vue.nextTick;
compat.observable = () => ({});
compat.version = Vue.version;

export default compat;
export * from "vue";
