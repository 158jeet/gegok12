<template>
  <div class="mt-6">

    <!-- Title -->
    <h2 class="text-gray-700 text-lg font-semibold italic">
      Photos ({{ photos.length }})
    </h2>

    <!-- Thumbnail -->
    <div v-if="photos.length" class="mt-4">
      <img
        :src="photos[0].path"
        class="w-32 h-32 object-cover border border-gray-300 p-1 cursor-pointer hover:opacity-80 transition"
        @click="openModal"
      />
    </div>

    <!-- FULLSCREEN LIGHTBOX -->
    <div
      v-if="showImage"
      class="fixed inset-0 bg-black bg-opacity-90 z-50 flex items-center justify-center"
    >

      <!-- Close -->
      <button
        @click="closeModal"
        class="absolute top-6 right-8 text-white text-4xl z-50"
      >
        &times;
      </button>

      <!-- Swiper -->
      <Swiper
        :modules="modules"
        :slides-per-view="1"
        :navigation="true"
        :pagination="{ clickable: true }"
        :loop="true"
        class="w-full h-full"
      >
        <SwiperSlide
          v-for="photo in photos"
          :key="photo.id"
        >
          <div class="w-full h-screen flex items-center justify-center p-6">
            <img
              :src="photo.path"
              class="max-h-full max-w-full object-contain"
            />
          </div>
        </SwiperSlide>
      </Swiper>

    </div>

  </div>
</template>

<script>
import { Swiper, SwiperSlide } from "swiper/vue";
import { Navigation, Pagination } from "swiper/modules";

import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";

import axios from "axios";

export default {
  props: ["event_id"],

  components: {
    Swiper,
    SwiperSlide,
  },

  data() {
    return {
      photos: [],
      showImage: false,
      modules: [Navigation, Pagination],
    };
  },

  mounted() {
    this.getSliderImage();
  },

  methods: {
  async getSliderImage() {
    try {
      const response = await axios.get(
        "/admin/display/photos/" + this.event_id
      );
      this.photos = response.data.data;
    } catch (error) {
      console.error(error);
    }
  },

  openModal() {
    this.showImage = true;
    document.body.style.overflow = "hidden"; // prevent background scroll
  },

  closeModal() {
    this.showImage = false;
    document.body.style.overflow = "auto";
  }
},
};
</script>