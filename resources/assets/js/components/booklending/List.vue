<template>
  <div>
    <vue-good-table
      :columns="columns"
      :rows="rows"
      :search-options="{ enabled: true }"
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
          filterOptions: {
            enabled: true,
          },
        },
        {
          label: "Return Date",
          field: "return_date",
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
      const response = await axios.get(
        "/student/libraryactivity/show"
      );
      this.rows = response.data.data;
    },
  },
};
</script>