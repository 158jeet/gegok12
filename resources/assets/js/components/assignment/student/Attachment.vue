<template>
  <div class="relative">
    <div class="modal-body">

      <div class="mb-2">
        <label class="tw-form-label">Attachment</label>
      </div>

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

      <div class="my-6">
        <button
          type="button"
          class="btn btn-submit blue-bg text-white rounded px-3 py-1"
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

Dropzone.autoDiscover = false;

export default {
  props: ["url", "id"],

  data() {
    return {
      myDropzone: null,
    };
  },

  mounted() {
    this.myDropzone = new Dropzone(this.$refs.dropzoneForm, {
      url: this.url + "/student/assignment/add",
      method: "post",
      headers: {
        "X-CSRF-TOKEN":
          document.head.querySelector("[name=csrf-token]").content,
      },
      paramName: "assignment_file",   // IMPORTANT
      maxFilesize: 8,
      maxFiles: 6,
      parallelUploads: 6,
      acceptedFiles: ".jpg,.jpeg,.png",
      autoProcessQueue: false,
      addRemoveLinks: true,
    });

    this.myDropzone.on("sending", (file, xhr, formData) => {
      formData.append("assignment_id", this.id);
    });

    this.myDropzone.on("queuecomplete", () => {
      this.myDropzone.removeAllFiles();
    });
  },

  beforeUnmount() {
    if (this.myDropzone) {
      this.myDropzone.destroy();
    }
  },

  methods: {
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