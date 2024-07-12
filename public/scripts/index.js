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
    function page(param) {
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
        current.textContent = c;
        el.querySelector("ul").outerHTML = markup.trim();
      });
    }
    prev.onclick = () => {
      page(prevParam);
    };
    next.onclick = () => {
      page(nextParam);
    };
  }

  // src/scripts/index.js
  var classMap = {
    "text-list": HomeDashboard
  };
  var actions = {
    lockScroll: "lock-scroll",
    unlockScroll: "unlock-scroll",
    showFieldError: "show-field-error"
  };
  function handleClicks() {
    document.body.addEventListener("click", (e) => {
      const link = e.target.closest("a");
      if (!link)
        return;
      if (link.matches('[href="#"]')) {
        e.preventDefault();
        const target = document.querySelector(link.href);
        if (target) {
          target.scrollIntoView({ behavior: "smooth" });
        }
      }
    });
  }
  function handleVideos() {
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
  }
  function handleModals() {
    const loadModal = (string) => {
      const dialog = document.querySelector("body > dialog");
      dialog.querySelector("section").innerHTML = string;
      dialog.showModal();
    };
    document.querySelector("body > dialog > button").onclick = ({ currentTarget }) => {
      currentTarget.parentElement.close();
    };
    document.querySelectorAll("img[data-bigable]").forEach((image) => {
      image.onclick = () => {
        loadModal(`<img src="${image.src}" alt="${image.alt}" />`);
      };
    });
  }
  function handleDOMConentLoaded() {
    function cb() {
      handleClicks();
      handleVideos();
      handleModals();
      if (window.location.hash) {
        const target = document.querySelector(window.location.hash);
        if (target) {
          target.scrollIntoView({ behavior: "smooth" });
        }
      }
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
