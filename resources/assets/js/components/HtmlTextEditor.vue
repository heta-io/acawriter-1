<template>
    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Text Analyser</div>
                    <div class="card-body">
                        <div id="editor">
                            <froala :tag="'textarea'" :config="config" v-model="feditorContent"></froala>
                            <br />
                            <textarea v-model="editorContent"  class="form-control"></textarea>
                            <hr />
                            <!--<h4>GraphQl data</h4>
                            <div v-if="loading" class="loading">Loading .....</div>
                             <article v-for="employee in viewer.employeeList">
                                 {{employee.firstName}} {{employee.lastName}}  ({{employee.birthDate}})
                            </article>
                            <h3>Products</h3>
                            <ul>
                                <li v-for="product in viewer.productList" class="list-item-group">
                                    {{product.name}} : ${{product.unitPrice}}
                                </li>
                            </ul>-->
                            <ul v-if="errors && errors.length">
                                <li v-for="error in errors">{{error.message}}</li>
                            </ul>
                            <span v-show="tapCalls.vocab" class="fa fa-spinner fa-spin"></span>
                            <h4>TAP Preview:</h4>
                            <div class="row">

                                <span v-show="tapCalls.athanor" class="fa fa-spinner fa-spin"></span>
                                <div class="col-md-12" v-for="feed in tap">
                                    [<span class="badge bg-primary">{{feed.tags}}</span>]
                                    {{feed.str}}
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Features</div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            <i class="fa fa-globe"></i> TAP <small>next updated after : {{10- counter}} changes.</small>
                        </div>
                        <div class="alert alert-success"><i class="fa fa-database" aria-hidden="true"></i> <small>Save: {{auto}} </small></div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">Features</div>
                    <div class="card-body">
                        <ul>
                            <li class="list-item-group">Vocabulary: <span class="badge badge-info">{{vocab}} </span></li>
                            <li class="list-item-group">Athanor</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

</template>

<script>
    import VueFroala from 'vue-froala-wysiwyg';
    Vue.use(VueFroala);

    import gql from 'graphql-tag';
    const POSTS_QUERY = gql`
   {
       viewer {
           employeeList {
               employeeID,
               firstName,
               lastName,
               birthDate
           },
           productList {
               name,
               unitPrice
           }
       }
   }
   `;

    export default {
        name: 'editor',
        data () {
            return {
                preview: '',
                editLog : [],
                config: {
                    events: {
                        'froalaEditor.contentChanged': function (e, editor) {

                        }
                    }
                },
                editorContent: 'Edit Your Content Here!',
                feditorContent: 'Edit Your Content Here!',
                tmp: 'More text',
                viewer : {},
                loading: 0,
                tap:[],
                qtap:[],
                errors:[],
                tapCalls:{
                    'athanor': false,
                    'vocab'  :false
                },
                vocab:'',
                counter:0,
                tempIds:[],
                auto:''
            }
        },
        apollo:{
            viewer: {
                query: POSTS_QUERY,
                loadingKey: 'loading'
            }
        },
        mounted () {
            console.log("mounted editor");
            console.log("lets prepopulate the first call" );
            this.editLog.push(this.editorContent);
            this.fetchAnalysis();
            //console.log(this.editorContent);
        },
        created() {
            this.auto = 'every 5m';
            //setInterval(this.storeAnalysedDrafts, 900000);
        },
        watch :{
            editorContent: function (newVal) {
                this.$data.counter++;
                if(this.$data.counter >= 10 ) {
                    newVal.replace(/<[^>]*>/g, '');
                    console.log(newVal.split(/[\n\r]/g), this.$data.tap);
                    this.checkEligibility(newVal.split(/[\n\r]/g), this.$data.tap);

                    this.editLog.push(this.editorContent);
                    //this.fetchAnalysis();
                    //var t = newVal.split(".");
                    //console.log(t);

                    //  if(typeof id!== 'undefined') {
                    //     if(t[id] !== 'undefined') this.quickAnalyse(t[id]);
                    //   }
                }
                //axios.post('http://athanor.utscic.edu.au/v2/analyse/text/rhetorical', {'data': this.editorContent})
//                     .then(r => console.log('r:', JSON.stringify(r,null,2)));
            }
        },
        methods: {
            fetchAnalysis() {
                console.log("into fetch");
                // this.$data.tap='';
                this.$data.tapCalls.athanor =true;
                this.$data.counter = 0;
                axios.post('/processor', {'txt': this.editLog, 'action': 'athanor'})
                    .then(response => {
                        this.$data.tap = response.data.athanor.responseTxt;
                        this.$data.tapCalls.athanor=false;
                    })
                    .catch(e => {
                        this.$data.errors.push(e)
                    });
                this.$data.tapCalls.vacab =true;
                axios.post('/processor', {'txt': this.editLog, 'action': 'vocab'})
                    .then(response => {
                        this.$data.tapCalls.vocab=false;
                        this.$data.vocab = response.data.vocab.unique;
                    })
                    .catch(e => {
                        this.$data.errors.push(e)
                    });
            },
            checkEligibility: function(nv, ov) {

                var changedText='';
                var self = this;
                nv.forEach(function(item, idx) {
                    // console.log(item);
                    if(typeof ov[idx]!=='undefined') {
                        if(ov[idx].str!= item) {
                            changedText = item;
                            //changedIds.push(idx);
                        } else {changedText = '';}
                    } else {
                        //changedIds.push(idx);
                        changedText = item;
                    }
                    if(changedText!=='' && changedText!=='<p>' && changedText!=='</p>') {
                        self.quickAnalyse(changedText, idx);
                    }
                });
                //console.log(this.parent.$data.tempIds);
            },
            quickAnalyse(changedText, idx) {

                this.$data.counter = 0;
                axios.post('/processor', {'txt': changedText, 'action': 'qathanor'})
                    .then(response => {
                        this.$data.tap[idx] = response.data.athanor;
                    })
                    .catch(e => {
                        this.$data.errors.push(e)
                    });

            },
            storeAnalysedDrafts() {
                console.log("into auto store");
                // this.$data.tap='';
                this.$data.auto='processing....';

                axios.post('/processor', {'txt': this.editorContent, 'action': 'auto'})
                    .then(response => {
                        this.$data.auto = 'Done';
                    })
                    .catch(e => {
                        this.$data.errors.push(e)
                    });
                /*this.$data.tapCalls.vacab =true;
                axios.post('/processor', {'txt': this.editLog, 'action': 'vocab'})
                    .then(response => {
                        this.$data.tapCalls.vocab=false;
                        this.$data.vocab = response.data.vocab.unique;
                    })
                    .catch(e => {
                        this.$data.errors.push(e)
                    });
                */
            }
        }
    }
</script>