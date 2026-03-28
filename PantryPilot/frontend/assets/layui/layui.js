window.layui = {
  layer: {
    msg(text) {
      window.alert(text);
    }
  },
  use(modules, fn) {
    fn();
  }
};

document.addEventListener("DOMContentLoaded", () => {
  const tabs = document.querySelectorAll(".layui-tab-title li");
  const panes = document.querySelectorAll(".layui-tab-item");
  tabs.forEach((tab, idx) => {
    tab.addEventListener("click", () => {
      tabs.forEach((t) => t.classList.remove("layui-this"));
      panes.forEach((p) => p.classList.remove("layui-show"));
      tab.classList.add("layui-this");
      panes[idx].classList.add("layui-show");
    });
  });
});
