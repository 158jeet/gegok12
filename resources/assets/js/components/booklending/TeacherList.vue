<template>
  <div>
    <vue-good-table
      :columns="columns"
      :rows="rows"
      :search-options="{
        enabled: true,
        skipDiacritics: true,
        placeholder: 'Search this table'
      }"
      :sort-options="{ enabled: true }"
      :pagination-options="{ enabled: true, perPage: 10 }"
    />
  </div>
</template>

<script>
import { VueGoodTable } from "vue-good-table-next";
import "vue-good-table-next/dist/vue-good-table-next.css";
import axios from "axios";

export default {
  components: { VueGoodTable },

  data() {
    return {
      columns: [
        {
          label: "Book Code",
          field: "book_code_no",
          filterOptions: {
            enabled: true,
            placeholder: "Filter Book Code",
          },
        },
        {
          label: "Book Name",
          field: "title",
          filterOptions: {
            enabled: true,
            placeholder: "Filter Book Name",
          },
        },
        {
          label: "ISBN",
          field: "isbn_number",
          filterOptions: {
            enabled: true,
            placeholder: "Filter ISBN",
          },
        },
        {
          label: "Lent Date",
          field: "issue_date",
          type: "date",
          dateInputFormat: "yyyy-MM-dd",
          dateOutputFormat: "yyyy-MM-dd",
          filterOptions: {
            enabled: true,
          },
        },
        {
          label: "Return Date",
          field: "return_date",
          type: "date",
          dateInputFormat: "yyyy-MM-dd",
          dateOutputFormat: "yyyy-MM-dd",
          filterOptions: {
            enabled: true,
          },
        },
      ],
      rows: [],
    };
  },

  mounted() {
    this.getData();
  },

  methods: {
    async getData() {
      try {
        const response = await axios.get(
          "/teacher/libraryactivity/show"
        );
        this.rows = response.data.data;
      } catch (error) {
        console.error(error);
      }
    },
  },
};
</script>