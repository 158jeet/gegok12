<template>
  <div class="relative">
    <div class="modal-body">

      <div class="flex flex-col lg:flex-row w-full">
        <div class="tw-form-group w-full">
          <div class="lg:mr-8 md:mr-8">

            <div class="mb-2">
              <label class="tw-form-label">Attachment</label>
            </div>

            <!-- Dropzone -->
            <form
              ref="dropzoneForm"
              class="dropzone border border-dashed p-6 rounded"
            ></form>

            <button
              type="button"
              class="btn btn-reset reset-btn mt-2"
              @click="removeAllFiles"
            >
              Remove All Files
            </button>

            <span
              v-if="errors.attachment"
              class="text-red-500 text-xs font-semibold"
            >
              {{ errors.attachment[0] }}
            </span>

          </div>
        </div>
      </div>

      <div class="my-6">
        <button
          type="button"
          class="btn btn-submit blue-bg text-white rounded px-3 py-1 mr-3 text-sm font-medium"
          @click="submitForm"
        >
          Submit
        </button>
      </div>

    </div>
  </div>
</template>

<script>
import Dropzone from "dropzone";
import "dropzone/dist/dropzone.css";
import { bus } from "../../../app";

Dropzone.autoDiscover = false;

export default {
  props: ["url", "id", "mode"],

  data() {
    return {
      myDropzone: null,
      errors: {},
    };
  },

  mounted() {
    this.initDropzone();
  },

  beforeUnmount() {
    if (this.myDropzone) {
      this.myDropzone.destroy();
    }
  },

  methods: {
    initDropzone() {
      this.myDropzone = new Dropzone(this.$refs.dropzoneForm, {
        url: `${this.url}/${this.mode}/homework/add/${this.id}`,
        method: "post",
        headers: {
          "X-CSRF-TOKEN":
            document.head.querySelector("[name=csrf-token]").content,
        },
        paramName: "file",   // backend should use $request->file('file')
        maxFilesize: 8,
        maxFiles: 6,
        parallelUploads: 6,
        acceptedFiles: ".jpg,.jpeg,.png",
        autoProcessQueue: false,
        addRemoveLinks: true,
      });

      // Success
      this.myDropzone.on("success", (file, response) => {
        bus.emit("success", response);
      });

      // Error handling
      this.myDropzone.on("error", (file, errorMessage) => {
        console.error(errorMessage);
      });

      // After all uploads complete
      this.myDropzone.on("queuecomplete", () => {
        this.myDropzone.removeAllFiles();
      });
    },

    submitForm() {
      if (!this.myDropzone.getAcceptedFiles().length) {
        alert("Please select at least one file.");
        return;
      }

      this.myDropzone.processQueue();
    },

    removeAllFiles() {
      this.myDropzone.removeAllFiles(true);
    },
  },
};
</script>