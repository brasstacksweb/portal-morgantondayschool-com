(() => {
  // src/scripts/components.js
  function emit(handle, payload, target = window) {
    const event = new CustomEvent(handle, { detail: payload });
    target.dispatchEvent(event);
  }
  function on(handle, cb, target = window) {
    target.addEventListener(handle, cb);
  }
  function pop({
    container = document.body,
    classMap: classMap2 = {},
    actions: actions2 = {},
    cb = null
  }) {
    const events = { emit, on };
    function refresh(c = null) {
      if (c === null) {
        return;
      }
      pop({ container: c, classMap: classMap2, actions: actions2, cb });
    }
    Object.entries(classMap2).forEach(([name, component]) => {
      container.querySelectorAll(`[data-comp-name="${name}"]`).forEach((node) => {
        component(node, {
          ...JSON.parse(node.getAttribute("data-comp-params")),
          actions: actions2,
          events,
          refresh
        });
      });
    });
    if (cb) {
      cb({ events, refresh });
    }
  }
  var components_default = pop;

  // src/scripts/utilities.js
  var quickHash = (str) => {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = (hash << 5) - hash + char;
      hash &= hash;
    }
    return new Uint32Array([hash])[0].toString(36);
  };

  // src/scripts/components/modal.js
  function Modal(el, {
    actions: actions2,
    events,
    refresh
  }) {
    const dialog = el.querySelector("dialog");
    const close = dialog.querySelector("button");
    const content = dialog.querySelector("section");
    let currentContent = "";
    function handleKeyup({ key }) {
      if (key === "Escape") {
        events.emit(actions2.closeModal);
      }
    }
    function handleOpenModal() {
      events.emit(actions2.lockScroll);
      dialog.showModal();
      document.addEventListener("keyup", handleKeyup);
    }
    function handleCloseModal() {
      events.emit(actions2.unlockScroll);
      dialog.close();
      document.removeEventListener("keyup", handleKeyup);
    }
    function handleLoadModal(e) {
      const {
        markup
      } = e.detail;
      const newContent = quickHash(markup);
      if (newContent !== currentContent) {
        currentContent = newContent;
        content.innerHTML = markup;
        refresh(content);
      }
      events.emit(actions2.openModal);
    }
    events.on(actions2.openModal, handleOpenModal);
    events.on(actions2.closeModal, handleCloseModal);
    events.on(actions2.loadModal, handleLoadModal);
    close.onclick = () => {
      events.emit(actions2.closeModal);
    };
  }

  // src/scripts/components/text-list.js
  function HomeDashboard(el, {
    prevParam,
    nextParam,
    endpoint,
    paramName,
    loadingClass = "is-loading"
  }) {
    const current = el.querySelector("header > nav > span");
    const [
      prev,
      next
    ] = el.querySelectorAll("header > nav > button");
    function page(param, dir = 0) {
      el.setAttribute("data-dir", dir);
      el.classList.add(loadingClass);
      prev.disabled = true;
      next.disabled = true;
      fetch(`${endpoint}?${new URLSearchParams({
        [paramName]: param
      })}`, {
        headers: { Accept: "application/json" }
      }).then((res) => res.json().then((json) => ({
        status: res.status,
        ...json
      }))).then(({
        status,
        markup = "",
        prevParam: p = "",
        nextParam: n = "",
        current: c = ""
      }) => {
        if (status !== 200)
          return;
        el.classList.remove(loadingClass);
        prev.disabled = false;
        next.disabled = false;
        prevParam = p;
        nextParam = n;
        current.innerHTML = c;
        el.querySelector("ul").outerHTML = markup.trim();
      });
    }
    prev.onclick = () => {
      page(prevParam, -1);
    };
    next.onclick = () => {
      page(nextParam, 1);
    };
  }

  // src/scripts/index.js
  var classMap = {
    modal: Modal,
    "text-list": HomeDashboard
  };
  var actions = {
    lockScroll: "lock-scroll",
    unlockScroll: "unlock-scroll",
    openModal: "open-modal",
    closeModal: "close-modal",
    loadModal: "load-modal",
    showFieldError: "show-field-error"
  };
  function handleDOMConentLoaded() {
    function cb({ events }) {
      const header = document.querySelector(".header");
      document.querySelector(":root").style.setProperty("--h-header", `${header.offsetHeight}px`);
      document.body.addEventListener("click", (e) => {
        const link = e.target.closest("a");
        if (!link)
          return;
        if (link.href.includes("#") && link.target !== "_blank") {
          const target = document.getElementById(link.href.split("#")[1]);
          if (target) {
            e.preventDefault();
            header.querySelector('[type="checkbox"]#nav-toggle').checked = false;
            target.scrollIntoView({ behavior: "smooth" });
          }
        }
      });
      const videoObserver = new IntersectionObserver((entries) => {
        entries.forEach(({ target, isIntersecting }) => {
          if (isIntersecting) {
            target.play();
          } else {
            target.pause();
          }
        });
      }, { threshold: 0.1 });
      Array.from(document.querySelectorAll("video")).filter((v) => v.hasAttribute("playsinline")).forEach((v) => {
        videoObserver.observe(v);
      });
      document.querySelectorAll("img[data-big-url]").forEach((image) => {
        image.onclick = () => {
          const markup = `<img src="${image.dataset.bigUrl}" alt="${image.alt}" />`;
          events.emit(actions.loadModal, { markup });
        };
      });
    }
    components_default({ classMap, actions, cb });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", handleDOMConentLoaded);
  } else {
    handleDOMConentLoaded();
  }
})();
//# sourceMappingURL=index.js.map
