<template>
    <div class="overflow-x-scroll lg:overflow-x-auto md:overflow-x-auto py-3" v-bind:class="[this.profile_tab== 11 ?'block' :'hidden']">
        <div v-if="this.success!=null" class="alert alert-success mt-3" id="success-alert">{{this.success}}</div>

        <div class="">
            <ul class="flex flex-col lg:flex-row md:flex-row list-reset leading-loose my-2 text-sm">
                <li class="flex lg:px-4 md:px-2 py-1">
                    <div class="flex items-center">
                        <span class="text-gray-700 font-medium mx-2">
                            <svg version="1.0" class="w-5 h-5 bg-green-300 mx-1" xmlns="http://www.w3.org/2000/svg" width="225.000000pt" height="225.000000pt" viewBox="0 0 225.000000 225.000000" preserveAspectRatio="xMidYMid meet"><g transform="translate(0.000000,225.000000) scale(0.100000,-0.100000)" fill="#000000" stroke="none"><path d="M330 1125 l0 -795 795 0 795 0 0 795 0 795 -795 0 -795 0 0 -795z m1545 0 l0 -750 -750 0 -750 0 -3 740 c-1 407 0 746 3 753 3 10 159 12 752 10 l748 -3 0 -750z"/></g></svg>
                        </span>
                    </div>
                    <div>
                        <p>Paid Fee</p>
                    </div>
                </li>
                <li class="flex pr-4 py-1">
                    <div class="flex items-center">
                        <span class="text-gray-700 font-medium mx-2">
                            <svg version="1.0" class="w-5 h-5 bg-red-300 mx-1" xmlns="http://www.w3.org/2000/svg" width="225.000000pt" height="225.000000pt" viewBox="0 0 225.000000 225.000000" preserveAspectRatio="xMidYMid meet"><g transform="translate(0.000000,225.000000) scale(0.100000,-0.100000)" fill="#000000" stroke="none"><path d="M330 1125 l0 -795 795 0 795 0 0 795 0 795 -795 0 -795 0 0 -795z m1545 0 l0 -750 -750 0 -750 0 -3 740 c-1 407 0 746 3 753 3 10 159 12 752 10 l748 -3 0 -750z"/></g></svg>
                        </span>
                    </div>
                    <div>
                        <p>Unpaid Fee</p>
                    </div>
                </li>
                <li class="flex lg:px-4 md:px-2 py-1">
                    <div class="flex items-center">
                        <span class="text-gray-700 font-medium mx-2">
                            <svg version="1.0" class="w-5 h-5 bg-gray-300 mx-1" xmlns="http://www.w3.org/2000/svg" width="225.000000pt" height="225.000000pt" viewBox="0 0 225.000000 225.000000" preserveAspectRatio="xMidYMid meet"><g transform="translate(0.000000,225.000000) scale(0.100000,-0.100000)" fill="#000000" stroke="none"><path d="M330 1125 l0 -795 795 0 795 0 0 795 0 795 -795 0 -795 0 0 -795z m1545 0 l0 -750 -750 0 -750 0 -3 740 c-1 407 0 746 3 753 3 10 159 12 752 10 l748 -3 0 -750z"/></g></svg>
                        </span>
                    </div>
                    <div>
                        <p>Unassigned Fee</p>
                    </div>
                </li>
                <li class="flex lg:px-4 md:px-2 py-1">
                    <div class="flex items-center">
                        <span class="text-red-500 font-medium mx-2">*</span>
                    </div>
                    <div>
                        <p> - Concession Applied</p>
                    </div>
                </li>
            </ul>
        </div>
        <div class="flex flex-wrap custom-table mx-3 my-3">
            <table class="w-full" v-if="Object.keys(this.fees).length > 0">
                <thead class="bg-grey-light">
                    <tr class="border-b">
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Fee Type</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Title</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Term</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Amount</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Paid On</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Notify Parents</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Actions</th>
                    </tr>
                </thead>   
                <tbody v-for="fee in fees">
                    <tr class="border-b" v-bind:class="fee.bg_class">
                        <td class="py-3 px-2"><p class="font-semibold text-xs">{{ fee.fee_type }}</p></td>
                        <td class="py-3 px-2">
                            <a :href="'/'+mode+'/feedetail/show/'+fee.id" class="font-semibold text-xs">{{ fee.name }}<span v-if="fee.concession_applied == 1" class="text-red-500 font-semibold text-xs">*</span></a>
                        </td>
                        <td class="py-3 px-2"><p class="font-semibold text-xs">{{ fee.term }}</p></td>
                        <td class="py-3 px-2"><p class="font-semibold text-xs">{{ fee.amount }}</p></td>
                        <td class="py-3 px-2"><p class="font-semibold text-xs">{{ fee.paid_on }}</p></td>
                        <td class="py-3 px-2"><p class="font-semibold text-xs">{{ fee.notify_parent }}</p></td>
                        <td class="py-3 px-2">
                            <div class="flex items-center">
                                <a href="#" class="font-semibold text-xs" @click="showEdit(fee.id)" title="Edit" v-if="fee.feePayment_id != null && fee.status == 0">
                                    <svg version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 477.873 477.873" xml:space="preserve" class="w-4 h-4 fill-current mx-1"><g><g><path d="M392.533,238.937c-9.426,0-17.067,7.641-17.067,17.067V426.67c0,9.426-7.641,17.067-17.067,17.067H51.2 c-9.426,0-17.067-7.641-17.067-17.067V85.337c0-9.426,7.641-17.067,17.067-17.067H256c9.426,0,17.067-7.641,17.067-17.067 S265.426,34.137,256,34.137H51.2C22.923,34.137,0,57.06,0,85.337V426.67c0,28.277,22.923,51.2,51.2,51.2h307.2 c28.277,0,51.2-22.923,51.2-51.2V256.003C409.6,246.578,401.959,238.937,392.533,238.937z"></path></g></g> <g><g><path d="M458.742,19.142c-12.254-12.256-28.875-19.14-46.206-19.138c-17.341-0.05-33.979,6.846-46.199,19.149L141.534,243.937 c-1.865,1.879-3.272,4.163-4.113,6.673l-34.133,102.4c-2.979,8.943,1.856,18.607,10.799,21.585 1.735,0.578,3.552,0.873,5.38,0.875c1.832-0.003,3.653-0.297,5.393-0.87l102.4-34.133c2.515-0.84,4.8-2.254,6.673-4.13 l224.802-224.802C484.25,86.023,484.253,44.657,458.742,19.142z M434.603,87.419L212.736,309.286l-66.287,22.135l22.067-66.202 L390.468,43.353c12.202-12.178,31.967-12.158,44.145,0.044c5.817,5.829,9.095,13.72,9.12,21.955 C443.754,73.631,440.467,81.575,434.603,87.419z"></path></g></g></svg>
                                </a>

                                <a href="#" v-if="fee.feePayment_id == null" title="Assign" @click="assignFee(fee.id)">
                                    <svg class="w-4 h-4 fill-current mx-1" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 409.6 409.6" style="enable-background:new 0 0 409.6 409.6;" xml:space="preserve"><g><g><path d="M392.533,187.733H221.867V17.067C221.867,7.641,214.226,0,204.8,0s-17.067,7.641-17.067,17.067v170.667H17.067 C7.641,187.733,0,195.374,0,204.8s7.641,17.067,17.067,17.067h170.667v170.667c0,9.426,7.641,17.067,17.067,17.067 s17.067-7.641,17.067-17.067V221.867h170.667c9.426,0,17.067-7.641,17.067-17.067S401.959,187.733,392.533,187.733z"/></g></g></svg>
                                </a>

                                <a href="#" v-if="fee.feePayment_id != null && fee.status == 0" title="Reset" @click="resetFee(fee.id,fee.feePayment_id)">
                                    <svg version="1.0" class="w-4 h-4 fill-current mx-1" xmlns="http://www.w3.org/2000/svg" width="200.000000pt" height="200.000000pt" viewBox="0 0 200.000000 200.000000" preserveAspectRatio="xMidYMid meet"><g transform="translate(0.000000,200.000000) scale(0.100000,-0.100000)" fill="#000000" stroke="none"><path d="M843 1886 c-350 -67 -621 -318 -714 -661 -32 -115 -32 -335 0 -450 88 -324 340 -570 668 -652 103 -26 323 -23 428 6 167 45 321 136 437 259 114 121 142 193 87 223 -36 19 -66 2 -108 -59 -145 -214 -423 -347 -689 -329 -111 8 -186 28 -287 76 -164 78 -281 194 -361 356 -53 108 -73 182 -81 297 -15 219 80 465 236 611 196 183 482 258 736 192 85 -22 230 -95 300 -153 49 -40 145 -142 133 -142 -2 0 -32 9 -68 20 -81 25 -95 25 -120 0 -23 -23 -26 -49 -7 -74 16 -22 312 -109 344 -101 30 7 34 17 84 192 41 146 42 153 25 177 -11 18 -25 26 -46 26 -36 0 -52 -22 -75 -105 -10 -33 -20 -63 -24 -67 -3 -4 -28 20 -54 53 -101 126 -293 246 -462 290 -103 26 -284 33 -382 15z"/><path d="M957 1602 c-15 -16 -17 -53 -17 -314 0 -163 3 -304 6 -313 7 -18 434 -235 463 -235 31 0 52 29 49 64 -3 30 -12 35 -200 131 l-198 100 0 270 c0 229 -2 274 -16 293 -19 27 -64 29 -87 4z"/></g></svg>
                                </a>

                                <a href="#" title="Update Payment Details" @click="paymentDetail(fee.id,fee.feePayment_id)" v-if="fee.status != null">
                                    <svg version="1.0" class="w-8 h-8 fill-current mx-1" xmlns="http://www.w3.org/2000/svg" width="225.000000pt" height="225.000000pt" viewBox="0 0 225.000000 225.000000" preserveAspectRatio="xMidYMid meet"><g transform="translate(0.000000,225.000000) scale(0.100000,-0.100000)" fill="#000000" stroke="none"><path d="M790 1578 l0 -113 -146 -76 -145 -76 145 -68 c109 -51 146 -73 146 -87 0 -10 -2 -18 -3 -18 -2 0 -73 33 -158 74 l-154 74 -3 -252 c-2 -173 1 -260 9 -279 23 -55 35 -57 429 -57 l362 0 -9 33 c-33 131 24 264 141 325 42 22 64 27 126 27 63 0 83 -4 123 -27 l47 -27 0 129 0 129 -37 -16 c-21 -8 -68 -31 -106 -49 -37 -19 -92 -45 -122 -59 -50 -22 -55 -22 -55 -6 0 13 38 36 145 86 l145 68 -145 76 -145 76 0 112 0 113 -295 0 -295 0 0 -112z m550 -194 l0 -265 -116 -55 c-63 -30 -128 -54 -142 -54 -15 0 -78 25 -139 55 l-113 54 0 265 0 266 255 0 255 0 0 -266z"/><path d="M970 1505 c0 -23 4 -24 62 -27 37 -2 63 -8 65 -15 3 -9 -13 -12 -61 -10 -65 2 -66 1 -66 -24 0 -25 1 -25 60 -21 38 3 60 0 60 -7 0 -13 -33 -31 -59 -31 -11 0 -22 -4 -26 -9 -4 -8 73 -163 90 -179 1 -2 12 2 23 9 20 12 20 13 -9 64 -35 62 -35 68 -6 84 13 7 31 25 41 41 11 20 23 28 36 25 16 -3 20 2 20 25 0 23 -4 27 -19 23 -11 -3 -21 2 -24 11 -5 12 0 16 18 16 20 0 25 5 25 25 l0 25 -115 0 -115 0 0 -25z"/><path d="M1455 1048 c-60 -22 -111 -66 -140 -120 -95 -173 45 -385 240 -365 246 25 310 356 90 469 -46 24 -147 33 -190 16z m95 -148 l0 -70 70 0 c63 0 70 -2 70 -20 0 -18 -7 -20 -70 -20 l-70 0 0 -70 c0 -63 -2 -70 -20 -70 -18 0 -20 7 -20 70 l0 70 -70 0 c-63 0 -70 2 -70 20 0 18 7 20 70 20 l70 0 0 70 c0 63 2 70 20 70 18 0 20 -7 20 -70z"/></g></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <table class="w-full" v-else>
                <thead class="bg-grey-light">
                    <tr class="border-b">
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Fee Type</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Title</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Term</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Amount</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Paid On</th>
                        <th class="text-left text-sm px-2 py-2 text-grey-darker">Notify Parents</th>
                    </tr>
                </thead>   
                <tbody>
                    <tr class="border-b">
                        <td colspan="6">
                            <p class="font-semibold text-s" style="text-align: center">No Records Found</p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="this.page_count>1">
                <paginate v-model="page" :page-count="this.page_count" :page-range="3" :margin-pages="1" :click-handler="getData" :prev-text="'<'" :next-text="'>'" :container-class="'pagination'" :page-class="'page-item'" prev-link-class="'prev'" :next-link-class="'next'"></paginate>
            </div>
        </div>
        <div v-for="fee in fees">
            <div class="modal modal-mask" v-if="show == 'edit_'+fee.id">
                <div class="modal-wrapper px-4">
                    <div class="modal-container w-full max-w-md px-8 mx-auto">
                        <div class="modal-header flex justify-between items-center">
                            <h2 v-if="type == 'edit_amount'">Edit Fees</h2>
                            <h2 v-if="type == 'edit_payment'">Add Fees Payment Detail</h2>
                            <button id="close-button" class="modal-default-button text-2xl py-1"  @click="closeModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <ul class="flex flex-col lg:flex-row md:flex-row list-reset leading-loose my-2 text-sm">
                                <li class="flex lg:px-4 md:px-2 py-1">
                                    <div class="flex items-center">
                                        <span class="text-gray-700 font-medium mx-2">Fee Group : </span>
                                    </div>
                                    <div>
                                        <p>{{ editfee.fee_group }}</p>
                                    </div>
                                </li>
                                <li class="flex pr-4 py-1">
                                    <div class="flex items-center">
                                        <span class="text-gray-700 font-medium mx-2">Title : </span>
                                    </div>
                                    <div>
                                        <p>{{ editfee.title }}</p>
                                    </div>
                                </li>
                            </ul>
                            <ul class="flex flex-col lg:flex-row md:flex-row list-reset leading-loose my-2 text-sm">
                                <li class="flex lg:px-4 md:px-2 py-1">
                                    <div class="flex items-center">
                                        <span class="text-gray-700 font-medium mx-2">Start Date : </span>
                                    </div>
                                    <div>
                                        <p>{{ editfee.start_date }}</p>
                                    </div>
                                </li>
                                <li class="flex lg:px-4 md:px-2 py-1">
                                    <div class="flex items-center">
                                        <span class="text-gray-700 font-medium mx-2">Term : </span>
                                    </div>
                                    <div>
                                        <p>{{ editfee.term }}</p>
                                    </div>
                                </li>
                            </ul>
                            <ul class="flex flex-col lg:flex-row md:flex-row list-reset leading-loose my-2 text-sm">
                                <li class="flex lg:px-4 md:px-2 py-1">
                                    <div class="flex items-center">
                                        <span class="text-gray-700 font-medium mx-2">End Date : </span>
                                    </div>
                                    <div>
                                        <p>{{ editfee.end_date }}</p>
                                    </div>
                                </li>
                                <li class="flex lg:px-4 md:px-2 py-1" v-if="type == 'edit_payment'">
                                    <div class="flex items-center">
                                        <span class="text-gray-700 font-medium mx-2">Amount : </span>
                                    </div>
                                    <div>
                                        <p>{{ editfee.paid_amount }}</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="modal-body" v-if="type == 'edit_amount'">
                            <div class="flex items-center">
                                <div class="w-full lg:w-1/4"> 
                                    <label for="amount" class="tw-form-label">Amount</label>
                                </div>
                                <div class="my-2 w-full lg:w-3/4">
                                    <input type="text" name="amount" v-model="amount" class="tw-form-control w-full" placeholder="Enter Amount">
                                    <span v-if="errors.amount" class="text-red-500 text-xs font-semibold">{{ errors.amount[0] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-body" v-if="type == 'edit_amount'">
                            <div class="flex items-center">
                                <div class="w-full lg:w-1/4"> 
                                    <label for="comments" class="tw-form-label">Comments</label>
                                </div>
                                <div class="my-2 w-full lg:w-3/4">
                                    <input type="text" name="comments" v-model="comments" class="tw-form-control w-full" placeholder="Enter Comments">
                                    <span v-if="errors.comments" class="text-red-500 text-xs font-semibold">{{ errors.comments[0] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="my-6" v-if="type == 'edit_amount'">
                            <a href="#" class="btn btn-submit blue-bg text-white rounded px-3 py-1 mr-3 text-sm font-medium" @click="updateFee(editfee.id,editfee.feepayment_id)">Update</a>
                        </div>
                        <div class="modal-body" v-if="type == 'edit_payment'">
                            <div class="flex">
                                <div class="w-full lg:w-1/4">
                                    <label for="paid_on" class="tw-form-label">Paid On</label>
                                </div>
                                <div class="w-full lg:w-3/4">
                                    <input type="date" name="paid_on" v-model="paid_on" class="tw-form-control w-full" id="paid_on">
                                    <span v-if="errors.paid_on" class="text-red-500 text-xs font-semibold">{{errors.paid_on[0]}}</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-body" v-if="type == 'edit_payment'">
                            <div class="flex items-center">
                                <div class="w-6">
                                    <input type="checkbox" name="notify_parent" v-model="notify_parent" class="tw-form-control w-full" @click="addNotify($event)">
                                </div>
                                <div class="mx-1"> 
                                    <label for="notify_parent" class="tw-form-label">Notify Parent</label>
                                </div>
                            </div>
                        </div>
                        <div class="my-6" v-if="type == 'edit_payment'">
                            <a href="#" class="btn btn-submit blue-bg text-white rounded px-3 py-1 mr-3 text-sm font-medium" @click="addPaymentDetail(editfee.fee_id,editfee.id)">Submit</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import { bus } from "../../../app";
    export default {
        props:['url','name','mode'],
        data () {
            return {
                profile_tab:'',
                fees:[],
                editfee:[], 
                total: 0,
                page: 1,
                page_count: 0,
                show:false,
                amount:'',
                comments:'',
                paid_on:'',
                notify_parent:'',
                type:'',
                errors:[],
                success:null,
            }
        },

        methods:
        {
            getData(page=1)
            {
                axios.get('/'+this.mode+'/student/show/fees/'+this.name+'?page='+page).then(response => {
                    this.fees = response.data.data;
                    this.page_count = response.data.meta.last_page;
                    this.total = response.data.meta.total;
                    //console.log(this.fees);   
                });
            },

            showEdit(id)
            {
                axios.get('/'+this.mode+'/student/fee/list/'+id+'/'+this.name).then(response => {
                    this.editfee = response.data;
                    this.amount  = response.data.amount;
                    this.comments  = response.data.comments;
                    this.feepayment_id  = response.data.feepayment_id;
                    //console.log(this.editfee);   
                });
                this.show = 'edit_'+id;
                this.type = 'edit_amount';
            },

            paymentDetail(id,feePayment_id)
            {
                axios.get('/'+this.mode+'/student/feepayment/add/list/'+feePayment_id).then(response => {
                    this.editfee = response.data;
                    this.paid_on = response.data.paid_on;
                    this.notify_parent = response.data.notify_parent;
                    //console.log(this.editfee);   
                });
                this.show = 'edit_'+id;
                this.type = 'edit_payment';
            },

            addNotify(e)
            {
                if (e.target.checked) 
                {
                    this.notify_parent = 1;
                }
                else
                {
                    this.notify_parent = 0;
                }
            },

            closeModal()
            {
              this.show = 0;
            },

            updateFee(id,feepayment_id)
            {
                this.errors=[];
                this.success=null; 

                let formData=new FormData();

                formData.append('fee_id',id); 
                formData.append('name',this.name);
                formData.append('amount',this.amount); 
                formData.append('comments',this.comments); 
                formData.append('feepayment_id',feepayment_id); 

                axios.post('/admin/student/feepayment/edit/'+feepayment_id,formData,{headers: {'Content-Type': 'multipart/form-data'}}).then(response => {    
                    this.closeModal(); 
                    this.success = response.data.success;
                }).catch(error => {
                    this.errors = error.response.data.errors;
                });
            },

            assignFee(id)
            {
                this.errors=[];
                this.success=null; 

                let formData=new FormData();

                formData.append('id',id); 
                formData.append('name',this.name); 

                axios.post('/admin/student/fee/assign',formData,{headers: {'Content-Type': 'multipart/form-data'}}).then(response => {     
                    this.success = response.data.success;
                    window.location.reload();
                }).catch(error => {
                    this.errors = error.response.data.errors;
                });
            },

            resetFee(id,feePayment_id)
            {
                this.errors=[];
                this.success=null; 

                let formData=new FormData();

                formData.append('id',id); 
                formData.append('name',this.name); 
                formData.append('feePayment_id',feePayment_id);

                axios.post('/admin/student/fee/reset',formData,{headers: {'Content-Type': 'multipart/form-data'}}).then(response => {     
                    this.success = response.data.success;
                    window.location.reload();
                }).catch(error => {
                    this.errors = error.response.data.errors;
                });
            },

            addPaymentDetail(id,feePayment_id)
            {
                this.errors=[];
                this.success=null; 

                let formData=new FormData();

                formData.append('fee_id',id); 
                formData.append('name',this.name); 
                formData.append('paid_on',this.paid_on); 
                formData.append('notify_parent',this.notify_parent); 
                formData.append('feePayment_id',feePayment_id);

                axios.post('/admin/student/feepayment/add',formData,{headers: {'Content-Type': 'multipart/form-data'}}).then(response => {     
                    this.success = response.data.success;
                    //window.location.reload();
                }).catch(error => {
                    this.errors = error.response.data.errors;
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

<style scoped>
    .modal-mask {
        position: fixed;
        z-index: 9998;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, .5);
        display: table;
        transition: opacity .3s ease;
    } 

    .modal-wrapper {
        display: table-cell;
        vertical-align: middle;
        overflow:auto;
    }

    .modal-container {
        margin: 0px auto;
        padding: 20px 30px;
        background-color: #fff;
        border-radius: 2px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, .33);
        transition: all .3s ease;
        /* height: 550px;*/
        overflow:auto;
    }

    .modal-header h3 {
        margin-top: 0;
        color: #42b983;
    }

    .modal-body {
        margin: 20px 0;
    }

    .modal-default-button {
        float: right;
    }

    /*
     * The following styles are auto-applied to elements with
     * transition="modal" when their visibility is toggled
     * by Vue.js.
     *
     * You can easily play with the modal transition by editing
     * these styles.
     */

    .modal-enter {
        opacity: 0;
    }

    .modal-leave-active {
        opacity: 0;
    }

    .modal-enter .modal-container,
    .modal-leave-active .modal-container {
        -webkit-transform: scale(1.1);
        transform: scale(1.1);
    }
</style>