<template>
  <div class="px-3 overflow-x-scroll lg:overflow-x-auto md:overflow-x-auto" v-bind:class="[this.profile_tab==9?'block' :'hidden']">
        <div class="custom-table">
            <table class="w-full overflow-x-auto"> <!-- profiletab-table -->
                <thead>
                    <tr>
                        <!-- <th>Book Code</th> -->
                        <th>Category</th>
                        <th>Author</th>
                        <th>Book Name</th>
                        <!-- <th>ISBN</th> -->
                        <th>Issue Date</th>
                        <th>Return Date</th>
                        <th>status</th>

                    </tr>
                </thead>
<tbody v-if="lent.length > 0">
                    <tr v-for="booklent in lent" v-bind:class="booklent.bg_class">
                        <!-- <td>{{ booklent.book_code_no }}</td> -->
                        <td>{{ booklent.category }}</td>
                        <td>{{ booklent.author }}</td>
                        <td>{{ booklent.title }}</td>
                     
                        <td>{{ booklent.issue_date }}</td>
                        <td>{{ booklent.return_date }}</td>
                          <td>{{ booklent.status }}</td>

                    </tr>
                </tbody>
                <tbody v-else>
                    <tr>
                        <td colspan="5">
                            <p class="font-semibold text-s" style="text-align: center">No Records Found</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
    import { bus } from "../../../app";
    export default {
        props:['url','name','mode'],
        data () {
            return {
                lent:[],
                profile_tab:'',
                errors:[],
                success:null,     
            } 
        },
        methods:
        {
            getData()
            {
                axios.get('/admin/teacher/show/libraryactivity/'+this.name).then(response => {
                    this.lent = response.data.data;
                    // console.log(this.lents)
                });
            },
        },
  
        created()
        {       
            this.getData();   
            bus.on("dataProfileTab", data => {
                if(data!='')
                {
                    this.profile_tab=data;                   
                }
            });      
        }
    }
</script>