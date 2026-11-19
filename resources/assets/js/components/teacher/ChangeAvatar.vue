<template>
  <div>
    <h1 class="admin-h1 my-3">Change Avatar</h1>

    <div class="bg-white shadow border border-grey p-5">

      <!-- Success Message -->
      <div v-if="success" class="alert alert-success">
        {{ success }}
      </div>

      <!-- Current Avatar -->
      <div v-if="avatarUrl && !image" class="mb-3">
        <img
          :src="avatarUrl"
          style="width:100px;height:100px;object-fit:cover;border-radius:50%;"
        />
      </div>

      <!-- File Input -->
      <div class="mb-3">
        <input type="file" @change="onFileChange" />
      </div>

      <!-- Cropper -->
      <div v-if="image" class="mb-3">
        <Cropper
          :src="image"
          :stencil-props="{ aspectRatio: 1 }"
          style="width:300px;height:300px;"
          ref="cropper"
        />
      </div>

      <!-- Error -->
      <div v-if="errors.avatar" class="text-red-500 text-xs">
        {{ errors.avatar[0] }}
      </div>

      <!-- Upload Button -->
      <div class="mt-3">
        <button
          class="submit-btn"
          :disabled="loading"
          @click="uploadImage"
        >
          {{ loading ? 'Uploading...' : 'Upload' }}
        </button>
      </div>

    </div>
  </div>
</template>

<script>
import axios from 'axios'
import { Cropper } from 'vue-advanced-cropper'
import 'vue-advanced-cropper/dist/style.css'

export default {
  props: ['url', 'mode'],

  components: {
    Cropper,
  },

  data() {
    return {
      avatarUrl: '',     // current saved avatar
      image: null,       // selected image for cropper
      errors: {},
      success: null,
      loading: false,
    }
  },

  methods: {

    // Load existing avatar
    async getAvatar() {
      try {
        const response = await axios.get(
          `${this.url}/${this.mode}/getavatar`
        )

        this.avatarUrl = response.data.avatar
      } catch (error) {
        console.error(error)
      }
    },

    // When user selects file
    onFileChange(e) {
      const file = e.target.files[0]
      if (!file) return

      this.image = URL.createObjectURL(file)
    },

    // Upload cropped image
    async uploadImage() {
      this.errors = {}
      this.success = null

      try {
        const { canvas } = this.$refs.cropper.getResult()

        if (!canvas) {
          this.errors.avatar = ['Please crop image']
          return
        }

        // Convert to base64
        const base64 = canvas.toDataURL('image/jpeg')

        const formData = new FormData()
        formData.append('avatar', base64)

        const response = await axios.post(
          `${this.url}/${this.mode}/changeavatar`,
          formData
        )

        this.success = response.data.message

        this.image = null

      } catch (error) {
        this.errors = error.response?.data?.errors || {}
      }
    },
  },

  created() {
    this.getAvatar()
  },
}
</script>