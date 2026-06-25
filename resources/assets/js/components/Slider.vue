<template>
  <div class="w-full">

    <Swiper
      :modules="modules"
      :slides-per-view="1"
      :loop="true"
      :autoplay="{ delay: 3000, disableOnInteraction: false }"
      :pagination="{ clickable: true }"
      class="mySwiper"
    >

      <SwiperSlide
        v-for="(slide, index) in slides"
        :key="index"
      >
        <div class="text-center px-6 py-8">

          <h3 class="font-extrabold my-4 text-xl">
            {{ slide.title }}
          </h3>

          <p class="text-sm mb-4">
            {{ slide.description }}
          </p>

          <img
            :src="url + slide.image"
            class="mx-auto max-h-96 object-contain"
          />

        </div>
      </SwiperSlide>

    </Swiper>

  </div>
</template>

<script>
import { Swiper, SwiperSlide } from "swiper/vue";
import { Autoplay, Pagination } from "swiper/modules";

import "swiper/css";
import "swiper/css/pagination";

export default {
  props: ["url"],

  components: {
    Swiper,
    SwiperSlide,
  },

  data() {
    return {
      modules: [Autoplay, Pagination],

      slides: [
        {
          title: "Automated Student Attendance Alerts",
          description:
            "Through the daily attendance module, parents will be sent notifications whenever their child is absent.",
          image: "/images/app-image/attendance.jpg",
        },
        {
          title: "Auto update daily homeworks",
          description:
            "Parents receive daily homework updates and can monitor academic progress.",
          image: "/images/app-image/homeworks.jpg",
        },
        {
          title: "Update lesson plans",
          description:
            "Teachers update lesson plans and parents stay informed about classroom activities.",
          image: "/uploads/lesson-plan.png",
        },
        {
          title: "Update class events",
          description:
            "Parents are updated on daily class events and academic activities.",
          image: "/uploads/events.png",
        },
        {
          title: "Notice board updates",
          description:
            "Parents can view all notice board updates directly in the app.",
          image: "/images/app-image/noticeboard.jpg",
        },
        {
          title: "Update school events",
          description:
            "School events and functions are shared with parents in real time.",
          image: "/images/app-image/schoolevents.jpg",
        },
        {
          title: "Update disciplinary records",
          description:
            "Parents receive updates about disciplinary records and progress.",
          image: "/images/app-image/discipline.jpg",
        },
        {
          title: "Fee reminders",
          description:
            "Automatic reminders for upcoming term or academic fees.",
          image: "/uploads/fees.png",
        },
        {
          title: "Complaints & feedbacks",
          description:
            "Parents can send feedback or complaints directly to the admin.",
          image: "/images/app-image/feedback.jpg",
        },
      ],
    };
  },
};
</script>

<style scoped>
.mySwiper {
  width: 100%;
}

.mySwiper img {
  max-width: 100%;
}
</style>