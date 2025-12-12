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

  // src/scripts/components/form.js
  function Form(el, {
    siteKey,
    redirectPath,
    actions: actions2,
    events
  }) {
    const errorMessage = el.querySelector("p");
    const form = el.querySelector("form");
    const submit = form.querySelector('[type="submit"]');
    const successMessage = el.querySelector("form + div");
    submit.removeAttribute("disabled");
    form.onsubmit = async (e) => {
      e.preventDefault();
      submit.setAttribute("disabled", "true");
      const body = new FormData(form);
      const token = await grecaptcha.enterprise.execute(siteKey, { action: "submit" });
      body.append("token", token);
      fetch("/", {
        method: "POST",
        headers: { Accept: "application/json" },
        body
      }).then((res) => res.json().then((json) => ({
        status: res.status,
        ...json
      }))).then(({
        status,
        message = "",
        errors = {}
      }) => {
        errorMessage.textContent = "";
        Array.from(body.keys()).map((name) => name.replace("[]", "")).forEach((name) => {
          events.emit(actions2.showFieldError, { name, errors: [] });
        });
        submit.removeAttribute("disabled");
        switch (status) {
          case 500:
            window.alert(message);
            break;
          case 400:
            errorMessage.textContent = message;
            Object.entries(errors).forEach(([name, errs]) => {
              events.emit(actions2.showFieldError, { name, errors: errs });
            });
            break;
          case 200:
          default:
            if (redirectPath) {
              window.location.href = redirectPath;
              return;
            }
            form.remove();
            successMessage.style.display = "block";
            el.parentElement.style.scrollMarginTop = "var(--h-header)";
            el.parentElement.scrollIntoView({ behavior: "smooth" });
        }
      });
    };
  }

  // src/scripts/components/form-field.js
  var toggleVisibility = (el, name, visible) => {
    if (visible) {
      el.querySelectorAll(`[name="${name}"]`).forEach((f) => f.setAttribute("required", "true"));
      el.style.display = "block";
      el.parentElement.style.display = "block";
    } else {
      el.querySelectorAll(`[name="${name}"]`).forEach((f) => f.removeAttribute("required"));
      el.style.display = "none";
      el.parentElement.style.display = "none";
    }
  };
  function FormField(el, {
    name,
    errorClass,
    conditionalName,
    conditionalValue,
    actions: actions2,
    events
  }) {
    const error = el.querySelector("p");
    events.on(actions2.showFieldError, ({ detail }) => {
      if (detail.name !== name)
        return;
      el.classList.toggle(errorClass, detail.errors.length > 0);
      error.textContent = detail.errors.join(", ");
    });
    if (conditionalName && conditionalValue) {
      const form = el.closest("form");
      const targets = form.querySelectorAll(`[name="${conditionalName}"]`);
      const formData = new FormData(form);
      targets.forEach((target) => {
        target.addEventListener("change", (e) => {
          toggleVisibility(el, name, e.currentTarget.value === conditionalValue);
        });
      });
      toggleVisibility(el, name, formData.get(conditionalName) === conditionalValue);
    }
  }

  // src/scripts/components/carousel.js
  function Form2(el) {
    const carousel = el.querySelector("ul");
    const [prev, next] = el.querySelectorAll("ul + nav > button");
    prev.onclick = () => {
      carousel.scrollBy({
        left: -1 * carousel.firstElementChild.offsetWidth,
        behavior: "smooth"
      });
    };
    next.onclick = () => {
      carousel.scrollBy({
        left: carousel.firstElementChild.offsetWidth,
        behavior: "smooth"
      });
    };
  }

  // src/scripts/components/install-banner.js
  function InstallBanner(el, {
    activeClass = "is-active"
  }) {
    const trigger = el.querySelector("button");
    let deferredPrompt;
    window.addEventListener("beforeinstallprompt", (e) => {
      e.preventDefault();
      deferredPrompt = e;
      el.classList.add(activeClass);
    });
    window.addEventListener("appinstalled", () => {
      deferredPrompt = null;
      el.classList.remove(activeClass);
    });
    trigger.onclick = async () => {
      deferredPrompt.prompt();
      await deferredPrompt.userChoice;
      deferredPrompt = null;
      el.classList.remove(activeClass);
    };
  }

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

  // src/scripts/components/notification-badge.js
  function NotificationBadge(element, { events, actions: actions2 }) {
    const badge = element.querySelector(".badge");
    let refreshInterval;
    function init() {
      fetchUnreadCount();
      refreshInterval = setInterval(fetchUnreadCount, 6e4);
      events.on("notification:refresh", fetchUnreadCount);
      window.addEventListener("beforeunload", cleanup);
    }
    async function fetchUnreadCount() {
      try {
        const response = await fetch("/registration/notifications/unread-count", {
          headers: { "Accept": "application/json" }
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const data = await response.json();
        updateBadge(data.count || 0);
      } catch (error) {
        console.error("Failed to fetch unread count:", error);
      }
    }
    function updateBadge(count) {
      if (count > 0) {
        badge.textContent = count > 99 ? "99+" : count.toString();
        badge.style.display = "inline-block";
        badge.setAttribute("aria-label", `${count} unread notifications`);
      } else {
        badge.style.display = "none";
        badge.removeAttribute("aria-label");
      }
    }
    function cleanup() {
      if (refreshInterval) {
        clearInterval(refreshInterval);
      }
    }
    init();
  }

  // src/scripts/components/updates-list.js
  function UpdatesList(element, { events, actions: actions2 }) {
    const updateItems = element.querySelectorAll("[data-update-id]");
    let observer;
    function handleIntersection(entries) {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const { updateId } = entry.target.dataset;
          if (updateId) {
            markAsRead(updateId, entry.target);
          }
        }
      });
    }
    async function markAsRead(updateId, element2) {
      try {
        const csrfToken = document.querySelector('[name="CRAFT_CSRF_TOKEN"]');
        if (!csrfToken) {
          console.warn("CSRF token not found");
          return;
        }
        const response = await fetch("/registration/notifications/mark-read", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-Token": csrfToken.value
          },
          body: JSON.stringify({ updateId: parseInt(updateId) })
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const result = await response.json();
        if (result.success) {
          element2.classList.add("read");
          observer.unobserve(element2);
          events.emit("notification:refresh");
        }
      } catch (error) {
        console.error("Failed to mark as read:", error);
      }
    }
    function cleanup() {
      if (observer) {
        observer.disconnect();
      }
    }
    observer = new IntersectionObserver(handleIntersection, {
      threshold: 0.5,
      rootMargin: "0px"
    });
    updateItems.forEach((item) => {
      if (!item.classList.contains("read")) {
        observer.observe(item);
      }
    });
    window.addEventListener("beforeunload", cleanup);
  }

  // src/scripts/index.js
  var classMap = {
    carousel: Form2,
    form: Form,
    "form-field": FormField,
    "install-banner": InstallBanner,
    modal: Modal,
    "text-list": HomeDashboard,
    NotificationBadge,
    UpdatesList
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
