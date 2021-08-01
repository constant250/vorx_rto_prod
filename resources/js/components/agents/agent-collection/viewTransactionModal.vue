<template>
    <modal
        name="viewTransactionModal"
        transition="nice-modal-fade"
        classes="verify-collection"
        :min-width="1000"
        :min-height="200"
        :pivot-y="0.1"
        :adaptive="true"
        :scrollable="true"
        :reset="true"
        width="40%"
        height="auto"
        style="padding-top:100px;"
        @opened="opened"
        @closed="closed"
    >
        
        <div class="card">
            <div>
                <div class="card card-header text-center">
                View Transaction
                </div>
                <div class="card card-body" style="overflow:auto;height:400px;">
                    <div class="card text-left" style="border:none">
                        <span><strong>Student:</strong> {{toType(data.student) !== 'undefined' ? data.student.party.name : ''}}</span>
                    </div>
                    <div class="card text-left" style="border:none">
                        <span><strong>Course:</strong>
                        <template v-if="toType(data.funded_student_course)!=='undefined'">
                            <template v-if="data.funded_student_course.course_code=='@@@@'">
                                Unit of Competency
                            </template>
                            <template v-else>
                                {{data.funded_student_course.course_code}} - {{data.funded_student_course.course.name}}
                            </template>
                        </template>
                        </span>
                        
                    </div>
                    <div class="card text-left" style="border:none">
                        <span><strong>Attachment: </strong>
                        <span v-if="toType(data.attachment)!=='undefined'">
                            <a :href="'/payment_attachment/'+data.attachment.id" target="_blank"> Go to link</a>
                        </span>
                        <span v-else>
                            No attachment found.
                        </span>
                        </span>
                    </div>
                    <div class="card text-left"  style="border:none">
                        <span>
                            <span class="fas fa-circle " style="color:#024b67"></span> 
                            <strong>Received Amount :</strong>  
                            {{amount_paid.toFixed(2)}} 
                        </span>
                    </div>
                    <div class="card text-left" style="border:none">
                        <span>
                            <span class="fas fa-circle " style="color:#858796"></span> 
                            <strong>Deducted Comission: </strong>
                            {{parseFloat(data.pre_deduc_comm).toFixed(2)}}
                        </span>
                    </div>
                    <div class="card text-left" style="border:none">
                        <span>
                            <strong>Total Amount: </strong>
                            {{(parseFloat(data.amount) + parseFloat(data.pre_deduc_comm)).toFixed(2)}}
                        </span>
                    </div>
                    <div class="card text-left" style="border:none">
                        <span>
                            <strong>Notes: </strong>
                            {{data.note}}
                        </span>
                    </div>
                    <div class="card text-left" style="border:none">
                        <span>
                            <strong>Remarks: </strong>
                            {{data.remakrs}}
                        </span>
                    </div>
                    <table
                        class="table custom-table"
                        id="dataTable"
                        width="100%"
                        cellspacing="0"
                        v-if="payment_schedule==null"
                    >
                         <thead>
                            <tr>
                                <th class='text-center' width="20%">Amount</th>
                                <th class='text-center' width="30%">Transaction #</th>
                                <th class='text-center' width="30%">Post Date</th>
                                <th class='text-center' width="30%">Agent</th>
                            </tr>
                         </thead>
                         <tbody>
                             <tr v-for="(st,index) in student_payment" :key="index">
                                 <td class="text-center">{{st.amount}}</td>
                                 <td class="text-center">{{st.transaction_code}}</td>
                                 <td class="text-center">{{st.payment_date | dateFormat}}</td>
                                 <td class="text-center">{{st.agent.agent_name}}</td>
                             </tr>
                         </tbody>
                    </table> 
                    <table
                        class="table custom-table"
                        id="dataTable"
                        width="100%"
                        cellspacing="0"
                        v-else
                    >       
                        <thead>
                            <tr>
                                <th class='text-center' width="10%">Month #</th>
                                <th class='text-center' width="15%">Amount Due</th>
                                <th class='text-center' width="15%">Due Date</th>
                                <!-- <th class='text-center' width="15%">Amount Received</th> -->
                                <th class='text-center' width="15%">Allocated Amount</th>
                                <th class='text-center' width="15%">Commission</th>
                                <th class='text-center' width="15%">Deducted Commission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(ps,index) in payment_schedule" :key="index">
                                 <td class="text-center">{{parseInt(index)+1}}</td>
                                 <!-- <td class="text-center">{{ps.balance.toFixed(2)}}</td> -->
                                 <td class="text-center">{{ps.balance.toFixed(2)}}</td>
                                 <td class="text-center">{{ps.due_date | dateFormat}}</td>
                                 <!-- <td class="text-center">{{getReceived(ps.approved_amount_paid,ps.prededucted_com)}}</td> -->
                                 <td class="text-center bg-primary text-white" v-if="toType(findPaymentDetail(ps.id))!=='undefined'">
                                        {{findPaymentDetail(ps.id)}}
                                </td>
                                <td class="text-center" v-else>
                                    0.00
                                </td>
                                <!-- <td v-if="ps.amountz!==0" class="text-center bg-primary text-white">
                                    {{ps.amountz}}
                                </td>
                                <td v-else class="text-center">
                                    0.00
                                </td> -->
                                 <td class="text-center">{{ps.comm_balance.toFixed(2)}}</td>
                                 <td class="text-center bg-secondary text-white" v-if="toType(findPreDeduct(ps.id))!=='undefined'">
                                     {{findPreDeduct(ps.id)}}
                                 </td>
                                 <td v-else class="text-center">
                                     0.00
                                 </td>
                             </tr>
                        </tbody>
                    </table>          
                </div>
            </div>
        </div>
    </modal>
</template>
<script>
import moment from 'moment'
export default {
    props:['data'],
    data(){
        return{
            student_payment:[],
            payment_schedule:[],
            amount_paid:0,
        }
    },
    filters:{
        dateFormat : function(data){
          return moment(data).format('DD/MM/YYYY');
        },
    },
    methods:{
        getReceived(a,b){
            let c = a-b;
            if(c<0){
                c=0;
            }
            return c.toFixed(2);
        },
        toType(obj) {
            return ({}).toString.call(obj).match(/\s([a-zA-Z]+)/)[1].toLowerCase();
        },
        findPreDeduct(id){
            for(let i = 0; i < this.student_payment.length; i++){
                if(id == this.student_payment[i].payment_schedule_template_id){
                    if(this.student_payment[i].pre_deduc_comm==0){
                        return undefined;
                    }else{
                        return this.student_payment[i].pre_deduc_comm
                    }
                }
            }
        },
        findPaymentDetail(id){
            // return id;
            // return id;
            var amount = 0;
            for(let i = 0; i < this.student_payment.length; i++){
                if(id == this.student_payment[i].payment_schedule_template_id){
                    if(this.student_payment[i].amount==0){
                        return undefined;
                    }else{
                        // return this.student_payment[i].amount
                        amount += parseFloat(this.student_payment[i].amount);
                    }
                }
            }
            if(amount==0){
                return undefined;
            }
            return amount.toFixed(2);
        },
        closed(){
            this.payment_schedule = [];
            this.student_payment = [];
            this.amount_paid = 0;
        },
        opened(e){
            this.amount_paid = parseFloat(this.data.amount);
            this.getTransaction();
        },
        getTransaction(){
            axios.get(`/agent/get-transaction/${this.data.id}/${this.data.transaction_code}`).then(
                response=>{
                    // console.log('gago');
                    this.payment_schedule = response.data.payment_sched_template;
                    this.student_payment = response.data.student_funded_payment_detail;  
                }
            );
        }
    }
}
</script>
<style scoped>
table.custom-table  th { background-color: transparent!important; color:gray!important; border: none!important;}
a.collapse-menu.active {
  background-color: #024b67;
  color: white;
}
.btn-square { border-radius: 0; }
.input-custom{
  background-color: #7573731f;
  border:none;
  border-radius: 0;
}
.custominput {
  border:none;
  border-radius: 0;
  text-align-last: center;
}
.pie {
  width: 25px; height: 25px;
  border-radius: 50%;
  background: white;
  background-image: linear-gradient(to right, transparent 50%, red 0);
}

.pie::before {
  content: ’;
  display: block;
  margin-left: 50%;
  height: 100%;
  border-radius: 0 100% 100% 0 / 30%;
  background-color: inherit;
  rotate: 45%;
}
</style>