(() => {
  // src/scripts/events.js
  var actions = {
    lockScroll: "lock-scroll",
    unlockScroll: "unlock-scroll",
    openModal: "open-modal",
    closeModal: "close-modal",
    loadModal: "load-modal",
    showFieldError: "show-field-error"
  };
  var emit = (handle, payload, target = window) => {
    const event = new CustomEvent(handle, { detail: payload });
    target.dispatchEvent(event);
  };
  var on = (handle, cb, target = window) => {
    target.addEventListener(handle, cb);
  };
  var events = { emit, on };

  // src/scripts/components/form.js
  var Form = class extends HTMLElement {
    constructor() {
      super();
      const { siteKey = "", redirectPath = "" } = this.dataset;
      const errorMessage = this.querySelector("p");
      const form = this.querySelector("form");
      const submit = form.querySelector('[type="submit"]');
      const successMessage = this.querySelector("form + div");
      submit.removeAttribute("disabled");
      form.onsubmit = async (e) => {
        e.preventDefault();
        submit.setAttribute("disabled", "true");
        const body = new FormData(form);
        const token = await grecaptcha.enterprise.execute(siteKey, { action: "submit" });
        body.append("token", token);
        const res = await fetch("/", {
          method: "POST",
          headers: { Accept: "application/json" },
          body
        });
        const { message = "", errors = {} } = await res.json();
        errorMessage.textContent = "";
        Array.from(body.keys()).map((name) => name.replace("[]", "")).forEach((name) => {
          events.emit(actions.showFieldError, { name, errors: [] });
        });
        submit.removeAttribute("disabled");
        switch (res.status) {
          case 500:
            window.alert(message);
            break;
          case 400:
            errorMessage.textContent = message;
            Object.entries(errors).forEach(([name, errs]) => {
              events.emit(actions.showFieldError, { name, errors: errs });
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
      };
    }
  };

  // src/scripts/components/form-field.js
  var toggleVisibility = (el2, name, visible) => {
    if (visible) {
      el2.querySelectorAll(`[name="${name}"]`).forEach((f) => f.setAttribute("required", "true"));
      el2.style.display = "block";
      el2.parentElement.style.display = "block";
    } else {
      el2.querySelectorAll(`[name="${name}"]`).forEach((f) => f.removeAttribute("required"));
      el2.style.display = "none";
      el2.parentElement.style.display = "none";
    }
  };
  var FormField = class extends HTMLElement {
    constructor(el2) {
      super();
      const {
        name,
        errorClass,
        conditionalName,
        conditionalValue
      } = this.dataset;
      const error = this.querySelector("p");
      events.on(actions.showFieldError, ({ detail }) => {
        if (detail.name !== name)
          return;
        this.classList.toggle(errorClass, detail.errors.length > 0);
        error.textContent = detail.errors.join(", ");
      });
      if (conditionalName && conditionalValue) {
        const form = this.closest("form");
        const targets = form.querySelectorAll(`[name="${conditionalName}"]`);
        const formData = new FormData(form);
        targets.forEach((target) => {
          target.addEventListener("change", (e) => {
            toggleVisibility(el2, name, e.currentTarget.value === conditionalValue);
          });
        });
        toggleVisibility(el2, name, formData.get(conditionalName) === conditionalValue);
      }
    }
  };

  // src/scripts/components/carousel.js
  var Carousel = class extends HTMLElement {
    constructor(el2) {
      super();
      const carousel = this.querySelector("ul");
      const [prev, next] = this.querySelectorAll("ul + nav > button");
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
  };

  // src/scripts/components/install-banner.js
  var InstallBanner = class extends HTMLElement {
    constructor() {
      super();
      const { activeClass = "is-active" } = this.dataset;
      const trigger = this.querySelector("button");
      let deferredPrompt;
      window.addEventListener("beforeinstallprompt", (e) => {
        e.preventDefault();
        deferredPrompt = e;
        this.classList.add(activeClass);
      });
      window.addEventListener("appinstalled", () => {
        deferredPrompt = null;
        this.classList.remove(activeClass);
      });
      trigger.onclick = async () => {
        deferredPrompt.prompt();
        await deferredPrompt.userChoice;
        deferredPrompt = null;
        this.classList.remove(activeClass);
      };
    }
  };

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
  var arrayBufferToBase64Url = (arrayBuffer) => {
    const uint8Array = new Uint8Array(arrayBuffer);
    let base64String = btoa(String.fromCharCode(...uint8Array));
    base64String = base64String.replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
    return base64String;
  };
  var base64ToUint8Array = (base64String) => {
    const padding = "=".repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  };

  // src/scripts/components/modal.js
  var Modal = class extends HTMLElement {
    constructor() {
      super();
      const dialog = this.querySelector("dialog");
      const close = dialog.querySelector("button");
      const content = dialog.querySelector("section");
      let currentContent = "";
      function handleKeyup({ key }) {
        if (key === "Escape") {
          events.emit(actions.closeModal);
        }
      }
      function handleOpenModal() {
        events.emit(actions.lockScroll);
        dialog.showModal();
        document.addEventListener("keyup", handleKeyup);
      }
      function handleCloseModal() {
        events.emit(actions.unlockScroll);
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
        }
        events.emit(actions.openModal);
      }
      events.on(actions.openModal, handleOpenModal);
      events.on(actions.closeModal, handleCloseModal);
      events.on(actions.loadModal, handleLoadModal);
      close.onclick = () => {
        events.emit(actions.closeModal);
      };
    }
  };

  // src/scripts/components/subscriptions.js
  var Subscriptions = class extends HTMLElement {
    constructor() {
      super();
      const {
        loadedClass = "has-loaded",
        activeClass = "is-active",
        vapidPublicKey
      } = this.dataset;
      const [subscribeForm, unsubscribeForm] = this.querySelectorAll("form");
      const body = this.querySelector("p");
      const [subscribeTrigger, unsubscribeTrigger] = this.querySelectorAll('button[type="button"]');
      if (!("Notification" in window) || !("serviceWorker" in navigator) || !("PushManager" in window)) {
        body.textContent = "Push notifications are not supported by your browser.";
        this.classList.add(loadedClass);
        return;
      }
      const { permission } = Notification;
      switch (permission) {
        case "granted":
          const unsubscribed = localStorage.getItem("unsubscribed");
          if (unsubscribed === "true") {
            this.classList.remove(activeClass);
            body.textContent = "You have unsubscribed from notifications. Click below to subscribe again.";
            break;
          }
          this.subscribe(subscribeForm, vapidPublicKey, activeClass);
          break;
        case "denied":
          this.classList.remove(activeClass);
          body.textContent = "You have blocked notifications. Please enable them in your browser settings.";
          subscribeTrigger.style.display = "none";
          break;
        case "default":
          this.classList.remove(activeClass);
          body.textContent = "Click below to enable notifications in your browser and subscribe to class updates.";
          break;
        default:
          this.classList.remove(activeClass);
          break;
      }
      subscribeTrigger.onclick = async () => {
        try {
          localStorage.removeItem("unsubscribed");
          await Notification.requestPermission();
          window.location.reload();
        } catch (error) {
          console.error("Error requesting notification permission:", error);
        }
      };
      unsubscribeTrigger.onclick = async () => {
        await this.unsubscribe(unsubscribeForm);
      };
      this.classList.add(loadedClass);
    }
    async subscribe(form, vapidPublicKey, activeClass) {
      try {
        let registration = await navigator.serviceWorker.getRegistration();
        if (!registration) {
          registration = await navigator.serviceWorker.register("/sw.js");
          await navigator.serviceWorker.ready;
        }
        const existingSubscription = await registration.pushManager.getSubscription();
        if (existingSubscription) {
          this.classList.add(activeClass);
          return;
        }
        const subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: base64ToUint8Array(vapidPublicKey)
        });
        const formData = new FormData(form);
        formData.append("endpoint", subscription.endpoint);
        formData.append("p256dhKey", arrayBufferToBase64Url(subscription.getKey("p256dh")));
        formData.append("authKey", arrayBufferToBase64Url(subscription.getKey("auth")));
        const res = await fetch("/", {
          method: "POST",
          headers: { Accept: "application/json" },
          body: formData
        });
        if (res.ok) {
          this.classList.add(activeClass);
          this.scrollIntoView({ behavior: "smooth" });
        } else {
          this.unsubscribe();
          throw new Error("Failed to save subscription");
        }
      } catch (error) {
        console.error("Error enabling notifications:", error);
      }
    }
    async unsubscribe(form) {
      try {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
          await subscription.unsubscribe();
        }
        if (!form) {
          return;
        }
        const formData = new FormData(form);
        formData.append("endpoint", subscription ? subscription.endpoint : "");
        const res = await fetch("/", {
          method: "POST",
          headers: { Accept: "application/json" },
          body: formData
        });
        if (res.ok) {
          localStorage.setItem("unsubscribed", "true");
          window.location.reload();
        } else {
          throw new Error("Failed to remove subscription from server");
        }
      } catch (error) {
        console.error("Error disabling notifications:", error);
      }
    }
  };

  // src/scripts/components/text-list.js
  var TextList = class extends HTMLElement {
    constructor() {
      super();
      const {
        endpoint,
        paramName,
        loadingClass = "is-loading"
      } = this.dataset;
      if (!endpoint || !paramName) {
        return;
      }
      let { prevParam, nextParam } = this.dataset;
      const current = this.querySelector("header > nav > span");
      const [
        prev,
        next
      ] = this.querySelectorAll("header > nav > button");
      const page = async (param, dir = 0) => {
        this.setAttribute("data-dir", dir);
        this.classList.add(loadingClass);
        prev.disabled = true;
        next.disabled = true;
        const res = await fetch(`${endpoint}?${new URLSearchParams({
          [paramName]: param
        })}`, { headers: { Accept: "application/json" } });
        if (!res.ok) {
          return;
        }
        const {
          markup = "",
          prevParam: p = "",
          nextParam: n = "",
          current: c = ""
        } = await res.json();
        this.classList.remove(loadingClass);
        prev.disabled = false;
        next.disabled = false;
        prevParam = p;
        nextParam = n;
        current.innerHTML = c;
        this.querySelector("ul").outerHTML = markup.trim();
      };
      prev.onclick = () => {
        page(prevParam, -1);
      };
      next.onclick = () => {
        page(nextParam, 1);
      };
    }
  };

  // src/scripts/index.js
  function handleDOMConentLoaded() {
    customElements.define("tl-carousel", Carousel);
    customElements.define("tl-form", Form);
    customElements.define("tl-form-field", FormField);
    customElements.define("tl-install-banner", InstallBanner);
    customElements.define("tl-modal", Modal);
    customElements.define("tl-subscriptions", Subscriptions);
    customElements.define("tl-text-list", TextList);
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
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", handleDOMConentLoaded);
  } else {
    handleDOMConentLoaded();
  }
})();
//# sourceMappingURL=index.js.map
