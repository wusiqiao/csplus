$.confirm = function(title, ok, cancel){
    var btnArray = ['是', '否'];
    mui.confirm(title, '提示', btnArray, function(e) {
        if (e.index == 0 && ok !== undefined) {
            ok.call(this);
        } else {
            if (cancel !== undefined) {
                cancel.call(this);
            }
        }
    })
}
function buildVueTree(treeData, treeEl, options){
    Vue.component("vue-tree", {
        template:
            `<li :class="current==model.id?'item active':'item'">
                <div :class="isFolder?'bold tree-folder':''">
                    <span v-if="isFolder" :class="open?'tree-node tree-node-open':'tree-node tree-node-close'" @click="toggle"></span>
                    <span class="tree-node-title" @click="click"> {{model.name}}</span>
                    <span class="tree-node tree-node-add" @click="add">&nbsp; </span>
                    <span class="tree-node tree-node-remove" @click="remove">&nbsp; </span>
                </div>
                <ul v-show="open" v-if="isFolder" class="vue-tree">
                    <vue-tree :class="current==child.id?'item active':'item'" v-for="child in model.children" :model="child"></vue-tree>
                </ul>
             </li>`,
        props: {
            model: Object,
            current:0
        },
        data: function () {
            return {
                open: false
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
            add: function () {
                if (this.model.children) {
                    var item = {};
                    var self = this;
                    if (options.add !== undefined){
                        options.add.call(self.$el, self.model, function(item){
                            self.model.children.push(item);
                        });
                    }
                    this.open = true;
                }
            },
            remove: function () {
                var self = this;
                var title = "确认删除该节点？";
                if (self.model.children && self.model.children.length) {
                    title = "确认删除该节点及所有子节点？";
                }
                if ($.confirm(title, function () {
                    if (options.remove !== undefined){
                        var result = options.remove.call(self.$el, self.model);
                        if (result){
                            if (self.$parent.model) {
                                removeTreeNode(self.$parent.model, self.model);
                            }
                        }
                    }else{
                        if (self.$parent.model) {
                            removeTreeNode(self.$parent.model, self.model);
                        }
                    }
                }));
            },
            click:function(event){
                this.current = this.model.id;
                if (options.click !== undefined){
                    item = options.click.call(this.$el, self.model);
                }
            }
        }
    });
    new Vue({
        el: treeEl,
        data: {
            treeData: treeData
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
