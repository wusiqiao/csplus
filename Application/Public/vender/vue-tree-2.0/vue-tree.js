function buildVueTree(vue_tree) {
    Vue.component("vue-tree", {
        template:
            `<li :class="'item item-'+model.id">
                <div :class="isFolder?'bold tree-folder':'tree-folder'">
                    <span v-if="isFolder" :class="open?'tree-node tree-node-open':'tree-node tree-node-close'" @click="toggle"></span>
                    <span v-else class='tree-node tree-node-child'></span>
                    <span class="tree-node-title" @click="click"> {{model.name}}</span>
                    <span class="tree-node tree-node-edit" @click="select">编辑</span>
                    <!--<span v-if="actions.remove" class="tree-node tree-node-remove">&nbsp; </span>-->
                </div>
                <div class="bottom-line"></div>
                <ul v-show="open" v-if="isFolder" :class="'vue-tree vue-tree-'+model.id">
                    <vue-tree v-for="child in model.children" :model="child"></vue-tree>
                </ul>
             </li>`,
        props: {
            model: Object,
            actions: Object
        },
        data: function () {
            return {
                open: true
            }
        },
        computed: {
            isFolder: function () {
                return this.model.children && this.model.children.length
            }
        },
        methods: {
            toggle: function () {
                if (this.isFolder) {
                    this.open = !this.open
                }
            },
            select: function (event) {
                window.vue_tree.current = this.model;
                window.vue_tree.select(this.model);
            },
            click:function(){
                window.vue_tree.current = this.model;
                window.vue_tree.click(this.model);
            }
        },
        mounted:function(){
            if (window.vue_tree != undefined) {
                window.vue_tree.child_mounted();
            }
        }
    });
}

function removeTreeNode(parent, item){
    var index = 0;
    $(parent.children).each(function () {
        if (this.id == item.id){
            parent.children.splice(index, 1);
        }else(
            removeTreeNode(this, item)
        )
        index++;
    });
}
