<template>
  <div>
    <h3>Agent Details</h3>
    <div>
        <div class="row mb-2 d-flex justify-content-between">
          <div class="col-md-6">
              <a href="/agent" v-bind:class="'btn btn-'+app_color+' text-primary text-light'">
              <i class="fas fa-long-arrow-alt-left"></i> Back
              </a>
          </div>
          <div class="col-md-6">
              
          </div>
        </div>
    </div>
    <nav>
      <div class="nav nav-tabs" id="nav-tab" role="tablist">
        <a v-bind:class="'nav-item nav-link-'+app_color+''" id="nav-info-tab" data-toggle="tab" href="#nav-info" role="tab" aria-controls="nav-info" aria-selected="true" >Info</a>
        <a v-bind:class="'nav-item nav-link-'+app_color" id="nav-attachments-tab" data-toggle="tab" href="#nav-attachments" role="tab" aria-controls="nav-attachments" aria-selected="false" >Attachments</a>
        <a v-bind:class="'nav-item nav-link-'+app_color" id="nav-commission-settings-tab" data-toggle="tab" href="#nav-commission-settings" role="tab" aria-controls="nav-prospectus" aria-selected="false">Commission Settings</a>
        <a v-bind:class="'nav-item nav-link-'+app_color+''" id="nav-collections-tab" data-toggle="tab" href="#nav-collections" role="tab" aria-controls="nav-collections" aria-selected="false">Collections</a>
        <a v-bind:class="'nav-item nav-link-'+app_color" id="nav-pending-commission-tab" data-toggle="tab" href="#nav-pending-commission" role="tab" aria-controls="nav-pending-commission" aria-selected="false">Pending Commission</a>
        <!-- <a v-bind:class="'nav-item nav-link-'+app_color" id="nav-commission-report-tab" data-toggle="tab" href="#nav-commission-report" role="tab" aria-controls="nav-commission-report" aria-selected="false">Commission Report</a> -->
        <!-- <a v-bind:class="'nav-item nav-link-'+app_color" id="nav-release-commission-tab" data-toggle="tab" href="#nav-release-commission" role="tab" aria-controls="nav-release-commission" aria-selected="false">Release Commission</a> -->
      </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
      <div class="tab-pane fade" id="nav-info" role="tabpanel" aria-labelledby="nav-info-tab">
        <agent-info></agent-info>
      </div>
      <div class="tab-pane fade" id="nav-attachments" role="tabpanel" aria-labelledby="nav-attachments-tab">
        <agent-attachment></agent-attachment>
      </div>
      <div class="tab-pane fade" id="nav-commission-settings" role="tabpanel" aria-labelledby="nav-commission-settings-tab">
        <agent-commission></agent-commission>
      </div>
      <div class="tab-pane fade" id="nav-collections" role="tabpanel" aria-labelledby="nav-collections-tab">
        <agent-collections />
      </div>
      <div class="tab-pane fade" id="nav-pending-commission" role="tabanel" aria-labelledby="nav-pending-commission-tab">
        <pending-commission />
      </div>
      <!-- <div class="tab-pane fade" id="nav-commission-report" role="tabpanel" aria-labelledby="nav-commission-report-tab">
        <agent-commission-report></agent-commission-report>
      </div> -->
      <!-- <div class="tab-pane fade" id="nav-release-commission" role="tabpanel" aria-labelledby="nav-release-commission-tab">
        <agent-release-commission></agent-release-commission>
      </div> -->
    </div>
  </div>
</template>


<script>
// import ToolbarActions from "../../components/globals/ToolbarActionsComponent.vue";
import AgentInfo from "./agentInfoComponent.vue";
import AgentAttachment from "./agentAttachmentComponent.vue";
import AgentCommission from "./agentCommissionComponent.vue";
import AgentCommissionReport from "./agentCommissionReportComponent.vue";
import AgentReleaseCommission from "./agentReleaseCommissionComponent.vue";
import AgentCollections from "./agentCollectionsComponent.vue"
import PendingCommission from './pendingCommissionComponent.vue';

export default {
  // mounted() {
  //     console.log('Component mounted.')
  // }
  components: {
      // ToolbarActions,
      AgentInfo,
      AgentAttachment,
      AgentCommission,
      AgentCommissionReport,
      AgentReleaseCommission,
      AgentCollections,
      PendingCommission
  },
  data() {
    return {
      app_color: app_color,
    };
  },
  mounted(){
    let tabId = localStorage.getItem('activeTab');
    if(tabId !==null){
      $(`#nav-tab a[href="#${tabId}"]`).tab('show');
      localStorage.removeItem('activeTab');
    }else{
      $(`#nav-tab a[href="#nav-info"]`).tab('show');
    }
  }
};
</script>

<style>
.tab-pane {
  border: 1px solid #e0dfdf;
  border-top: none;
  padding: 1.3rem;
  border-bottom-right-radius: 5px;
  border-bottom-left-radius: 5px;
  background-color: #ffffff;
}
</style>