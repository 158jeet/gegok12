<template>
  <div class="max-w-3xl mx-auto px-4 py-6">

    <!-- Title -->
    <h2 class="text-lg font-semibold text-gray-700 mb-4 italic">
      Upload Event Photos
    </h2>

    <!-- Upload Area -->
    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 transition hover:border-blue-500">

      <file-pond
        name="photos"
        ref="pond"
        allow-multiple
        :max-files="10"
        :instant-upload="false"
        accepted-file-types="image/*"
        label-idle="
          <div class='text-center'>
            <p class='text-gray-600 text-base'>
              Drag images here
            </p>
            <p class='text-sm text-blue-600 underline cursor-pointer'>
              Select Files
            </p>
          </div>
        "
        @updatefiles="handleFiles"
      />

    </div>

    <!-- Submit Button -->
    <button
      class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition disabled:opacity-50"
      :disabled="!files.length || isUploading"
      @click="saveImage"
    >
      {{ isUploading ? "Uploading..." : "Submit" }}
    </button>

    <!-- Slider -->
    <div class="mt-8">
      <PhotosSlider
        :event_id="event_id"
      />
    </div>

  </div>
</template>

<script>
import vueFilePond from "vue-filepond";
import FilePondPluginImagePreview from "filepond-plugin-image-preview";

import "filepond/dist/filepond.min.css";
import "filepond-plugin-image-preview/dist/filepond-plugin-image-preview.css";

import axios from "axios";
import PhotosSlider from "./PhotosSlider";

const FilePond = vueFilePond(FilePondPluginImagePreview);

export default {
  props: ["event_id"],

  components: {
    FilePond,
    PhotosSlider,
  },

  data() {
    return {
      files: [],
      isUploading: false,
    };
  },

  methods: {
    handleFiles(fileItems) {
      this.files = fileItems.map(item => item.file);
    },

    async saveImage() {
      if (!this.files.length) return;

      this.isUploading = true;

      const formData = new FormData();
      this.files.forEach(file => {
        formData.append("photos[]", file);
      });

      try {
        await axios.post(
          "/admin/upload/photos/" + this.event_id,
          formData,
          { headers: { "Content-Type": "multipart/form-data" } }
        );

        this.$refs.pond.removeFiles();
        this.files = [];

      } catch (error) {
        console.error(error);
      }

      this.isUploading = false;
    },
  },
};
</script>

<style>
.filepond--panel-root {
  border-radius: 8px;
}

.filepond--item-panel {
  background-color: #f9fafb;
}

.filepond--action-remove-item {
  color: #ef4444;
}
</style>