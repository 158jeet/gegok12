<template>
  <div class="w-full rounded h-64">

    <Swiper
      v-if="feeds.length"
      :modules="modules"
      :slides-per-view="1"
      :navigation="true"
      :loop="true"
      class="h-64"
    >

      <SwiperSlide
        v-for="list in feeds"
        :key="list.id"
      >
        <img
          :src="list.path"
          class="object-cover h-64 w-full"
        />
      </SwiperSlide>

    </Swiper>

  </div>
</template>

<script>
import { Swiper, SwiperSlide } from "swiper/vue";
import { Navigation } from "swiper/modules";

import "swiper/css";
import "swiper/css/navigation";

export default {
  props: ["url", "id", "mode"],

  components: {
    Swiper,
    SwiperSlide,
  },

  data() {
    return {
      feeds: [],
      modules: [Navigation],
    };
  },

  methods: {
    async getData() {
      try {
        const response = await axios.get(
          this.url + "/" + this.mode + "/classwall/post/showList/" + this.id
        );
        this.feeds = response.data.attachments;
      } catch (error) {
        console.error(error);
      }
    },
  },

  mounted() {
    this.getData();
  },
};
</script>

<style scoped>
.swiper {
  width: 100%;
  height: 100%;
}
</style>