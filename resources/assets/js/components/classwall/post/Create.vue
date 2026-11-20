<template>
    <div class="bg-white shadow px-4 py-3">
        <div v-if="this.success!=null" class="alert alert-success" id="success-alert">{{this.success}}</div>

        <div class="flex flex-col lg:flex-row w-full lg:w-3/5">
            <div class="tw-form-group w-full lg:w-3/4">
                <div class="lg:mr-8 md:mr-8">
                    <div class="mb-2">
                        <label for="description" class="tw-form-label">Description</label>
                    </div>
                    <div class="mb-2">
                        <QuillEditor
                              v-model:content="description"
                              contentType="html"
                              theme="snow"
                            />
                        <div class="text-gray-700 text-xs my-1" v-text="(500 - description.length)+'/'+500" style="text-align: right"></div>               
                    </div>
                    <span v-if="errors.description" class="text-red-500 text-xs font-semibold">{{errors.description[0]}}</span>
                </div> 
            </div>
        </div>
        <input type="hidden" v-if="this.description != null" name="description" :value="this.description">

        <div class="flex flex-col lg:flex-row w-full lg:w-3/5">
            <div class="tw-form-group w-full lg:w-3/4">
                <div class="lg:mr-8 md:mr-8">
                    <div class="mb-2">
                        <label for="attachment" class="tw-form-label">Attachment</label>
                    </div>
                    <div class="mb-2">
                        <div ref="dropzone" class="dropzone border rounded p-4 bg-gray-50"></div>
                        <a href="#" class="btn btn-reset reset-btn" @click="removeAllFiles()">Remove All Files</a> 
                    </div>
                    <span v-if="errors.attachment" class="text-red-500 text-xs font-semibold">{{errors.attachment[0]}}</span>
                </div> 
            </div>
        </div>

        <div class="flex flex-col lg:flex-row w-full lg:w-3/5">
            <div class="tw-form-group w-full lg:w-3/4">
                <div class="lg:mr-8 md:mr-8">
                    <div class="mb-2">
                        <label for="visibility" class="tw-form-label">Visibility<span class="text-red-500">*</span></label>
                    </div>
                    <div class="mb-2">
                        <select class="tw-form-control w-full" id="visibility" v-model="visibility" name="visibility">
                            <option value="" disabled>Select Visibility</option>
                            <option v-for="visible in visiblelist" v-bind:value="visible.id">{{ visible.name }}</option>
                        </select>
                    </div>
                    <span v-if="errors.visibility" class="text-red-500 text-xs font-semibold">{{errors.visibility[0]}}</span>
                </div> 
            </div>

            <div class="tw-form-group w-full lg:w-3/4" v-if="this.visibility == 'select_class'">
                <div class="lg:mr-8 md:mr-8">
                    <div class="mb-2">
                        <label for="visible_for" class="tw-form-label">Select Class<span class="text-red-500">*</span></label>
                    </div>
                    <div class="mb-2">
                        <select class="tw-form-control w-full" id="visible_for" v-model="visible_for" name="visible_for">
                            <option value="" disabled>Select Class</option>
                            <option v-for="standard in standardLinkList" v-bind:value="standard.id">{{ standard.standard_section }}</option>
                        </select>
                    </div>
                    <span v-if="errors.visible_for" class="text-red-500 text-xs font-semibold">{{errors.visible_for[0]}}</span>
                </div> 
            </div>
        </div>

        <div class="tw-form-group w-full lg:w-3/4">
            <div class="lg:mr-8 md:mr-8">
                <div class="mb-2">
                    <label for="tag" class="tw-form-label">Tag Name<span class="text-red-500"></span></label>
                </div>
                <div class="mb-2">
                    <input type="text" v-model="tag" name="tag" id="tag" class="tw-form-control w-1/2" >
                </div>
        
                <span v-if="errors.tag" class="text-red-500 text-xs font-semibold">{{errors.tag[0]}}</span>
            </div>
        </div>

        <div class="flex flex-col  w-full lg:w-3/5">
            <div class="tw-form-group w-full lg:w-3/4">
                <div class="flex items-center">
                    <div class="w-6">
                        <input type="checkbox" v-model="post_later" name="post_later" id="post_later" class="tw-form-control w-full" @click="showDate($event)">
                    </div>
                    <div class="mx-1">
                        <label for="post_later" class="tw-form-label">Post Later</label>
                    </div>
                    <span v-if="errors.post_later" class="text-red-500 text-xs font-semibold">{{errors.post_later[0]}}</span>
                </div>
            </div>

            <div class="tw-form-group w-full lg:w-3/4">
                <div class="lg:mr-8 md:mr-8 hidden" id="date">
                    <div class="mb-2">
                        <label for="posted_at" class="tw-form-label">Date Time<span class="text-red-500">*</span></label>
                    </div>
                    <div class="mb-2">
                        <VueDatePicker
                          v-model="posted_at"
                          format="dd-MM-yyyy HH:mm:ss"
                          model-type="format"
                          :enable-time-picker="true"
                          :is-24="true"
                          :auto-apply="true"
                          input-class-name="w-full rounded"
                        />
                    </div>
                    <span v-if="errors.posted_at" class="text-red-500 text-xs font-semibold">{{errors.posted_at[0]}}</span>
                </div> 
            </div>   
        </div>

        <div class="my-6">
            <a href="#" id="submit" class="btn btn-primary submit-btn" @click="submitForm()">Submit</a>
            <a href="#" class="btn btn-reset reset-btn" @click="resetForm()">Reset</a>
        </div>
    </div>
</template>

<script> 
    import { VueDatePicker } from '@vuepic/vue-datepicker'
    import '@vuepic/vue-datepicker/dist/main.css'

    import Dropzone from "dropzone"
    import "dropzone/dist/dropzone.css"
    Dropzone.autoDiscover = false

    import { QuillEditor } from '@vueup/vue-quill'
    import '@vueup/vue-quill/dist/vue-quill.snow.css'
    export default {
        components:{ 
            VueDatePicker ,
            QuillEditor,
        },
        props:['url' , 'entity_id' , 'entity_name' , 'mode'],
        data(){
            return {
                standardLinkList:[],
                post_id:'',
                description:'',
                visibility:'',
                visible_for:'',
                posted_at:'',
                tag:'',
                post_later:'',
                editorOption:{
                    theme: 'snow',
                    modules: {
                        toolbar: {
                            container: [
                                ['bold', 'italic', 'underline', 'strike'],       
                                [{ 'color': [] }, { 'background': [] }],
                                [{ 'script': 'sub' }, { 'script': 'super' }],        
                                [{ 'align': [] }],
                                ['image'],
                            ],      
                        }
                    } 
                },
                //visiblelist:[{id:'all_class', name:'All Classes'},{id:'select_class', name:'Select Class'}, {id:'select_page', name:'Select Page'}],
                visiblelist:[{id:'all_class', name:'All Classes'},{id:'select_class', name:'Class'}],
                errors:[],
                success:null,
                dropzoneInstance: null,
            }
        },
        mounted() {
          this.initDropzone()
        },

        methods:
        {
            getData()
            {
                axios.get(this.url+'/'+this.mode+'/classwall/post/add/list').then(response => {
                    this.standardLinkList = response.data.data;
                });
            },

            initDropzone() {
                  this.dropzoneInstance = new Dropzone(this.$refs.dropzone, {
                    url: this.url + '/' + this.mode + '/classwall/post/add/attachment',
                    method: 'post',
                    headers: {
                      "X-CSRF-TOKEN": document.head.querySelector("[name=csrf-token]").content
                    },

                    paramName: "file",        
                    uploadMultiple: true,     
                    parallelUploads: 6,       
                    maxFiles: 6,
                    maxFilesize: 0.5,
                    acceptedFiles: ".jpg,.jpeg,.png",
                    autoProcessQueue: false,
                    addRemoveLinks: true,
                  })

                  this.dropzoneInstance.on("sendingmultiple", (files, xhr, formData) => {
                    formData.append("post_id", this.post_id)
                  })

                  this.dropzoneInstance.on("queuecomplete", () => {
                    window.location.reload()
                  })
                },

            processQueue() {
              if (this.dropzoneInstance) {
                this.dropzoneInstance.processQueue()
              }
            },

            removeAllFiles() {
              if (this.dropzoneInstance) {
                this.dropzoneInstance.removeAllFiles(true)
              }
            },

            showTag(e)
            {
                if(e.target.checked)
                {
                    this.attach_tag=1;
                }
                else
                {
                    this.attach_tag=0;
                }
            },

            submitForm()
            {
                this.errors=[];
                this.success=null;

                let formData=new FormData(); 
                
                formData.append('entity_id',this.entity_id);          
                formData.append('entity_name',this.entity_name);        
                formData.append('description',this.description);
                formData.append('visibility',this.visibility);          
                formData.append('visible_for',this.visible_for);          
                formData.append('posted_at',this.posted_at);           
                formData.append('post_later',this.post_later);
                formData.append('tag',this.tag);

                axios.post(this.url+'/'+this.mode+'/classwall/post/add',formData,{headers: {'Content-Type': 'multipart/form-data'}}).then(response => { 
                    this.post_id = response.data.id
                    this.processQueue()
                    this.success = response.data.success;
                    if (this.dropzoneInstance.getQueuedFiles().length > 0) {
                        this.processQueue()
                      } else {
                        window.location.reload()
                      }
                }).catch(error => {
                    this.errors = error.response.data.errors;
                });
            },

            showDate(e)
            {
                if(e.target.checked)
                {
                    $('#date').addClass('block').removeClass('hidden');
                }
                else
                {
                    $('#date').addClass('hidden').removeClass('block');
                }
            },
        },
    
        created()
        {
            this.getData();
        }
    }
</script>