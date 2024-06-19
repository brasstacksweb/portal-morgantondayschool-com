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

  // src/scripts/components/cards-listing.js
  function CardsListing(el, {
    cardsContHandle,
    paginationLinksContHandle,
    labels,
    section,
    notIds,
    activeClass = "is-active"
  }) {
    const nav = el.querySelector("nav");
    const filtersToggle = nav.querySelector('[type="checkbox"]');
    const filtersLabel = nav.querySelector("label > span");
    const filters = nav.querySelectorAll("ul > li > a");
    const count = nav.querySelector("em");
    const featuredArticle = el.querySelector("article");
    const cardsCont = el.querySelector(cardsContHandle);
    const paginationLinksCont = el.querySelector(paginationLinksContHandle);
    let activeFilterIndex = [...filters].findIndex((f) => f.classList.contains(activeClass));
    function updateCards(url, push = true) {
      el.setAttribute("aria-busy", "true");
      fetch(`/json/cards-listing?${new URLSearchParams({
        section,
        notIds
      })}&${url.split("?")[1] || ""}`, {
        headers: { Accept: "application/json" }
      }).then((res) => res.json().then((json) => ({
        status: res.status,
        ...json
      }))).then(({
        status,
        info = null,
        cardsMarkup = "",
        paginationLinksMarkup = ""
      }) => {
        if (status !== 200)
          return;
        const activeFilter = filters[activeFilterIndex];
        const { first, last, total, currentPage } = info;
        const [s, p] = labels;
        const showFeaturedArticle = currentPage === 1 && activeFilterIndex === 0;
        const realTotal = showFeaturedArticle ? total + 1 : total;
        const realLast = showFeaturedArticle && total === last ? realTotal : last;
        el.removeAttribute("aria-busy");
        filtersToggle.checked = false;
        filtersLabel.textContent = activeFilter.textContent;
        filters.forEach((f) => {
          f.classList.toggle(activeClass, f === activeFilter);
        });
        count.innerHTML = `${first} to ${realLast} of ${realTotal} ${realTotal === 1 ? s : p}`;
        featuredArticle.setAttribute("aria-hidden", !showFeaturedArticle);
        cardsCont.innerHTML = cardsMarkup;
        paginationLinksCont.innerHTML = paginationLinksMarkup;
        nav.scrollIntoView({ behavior: "smooth" });
        if (push) {
          window.history.pushState({ activeFilterIndex }, "", url);
        }
      });
    }
    window.addEventListener("popstate", ({ state }) => {
      activeFilterIndex = state.activeFilterIndex;
      updateCards(window.location.href, false);
    });
    document.body.addEventListener("click", (e) => {
      if (paginationLinksCont.contains(e.target)) {
        e.preventDefault();
        const link = e.target.closest("a");
        updateCards(link.href);
      }
    });
    filters.forEach((f, i) => {
      f.onclick = (e) => {
        e.preventDefault();
        activeFilterIndex = i;
        updateCards(f.href);
      };
    });
    window.history.replaceState({ activeFilterIndex }, "", window.location.href);
  }

  // src/scripts/components/form.js
  function Form(el, {
    siteKey,
    actions: actions2,
    events
  }) {
    const form = el.querySelector("form");
    const submit = form.querySelector('[type="submit"]');
    const successMessage = el.querySelector("form + div");
    const grc = grecaptcha;
    submit.removeAttribute("disabled");
    form.onsubmit = (e) => {
      e.preventDefault();
      submit.setAttribute("disabled", "true");
      const body = new FormData(form);
      grc.ready(() => {
        grc.execute(siteKey, { action: "submit" }).then((token) => {
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
            Array.from(body.keys()).map((name) => name.replace("[]", "")).forEach((name) => {
              events.emit(actions2.showFieldError, { name, errors: [] });
            });
            submit.removeAttribute("disabled");
            switch (status) {
              case 500:
                window.alert(message);
                break;
              case 400:
                Object.entries(errors).forEach(([name, errs]) => {
                  events.emit(actions2.showFieldError, { name, errors: errs });
                });
                break;
              case 200:
              default:
                if (typeof dataLayer === "object") {
                  dataLayer.push({ event: "form_submit" });
                }
                form.remove();
                successMessage.style.display = "block";
                el.parentElement.style.scrollMarginTop = "var(--h-header)";
                el.parentElement.scrollIntoView({ behavior: "smooth" });
            }
          });
        });
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

  // src/scripts/components/home-dashboard.js
  var getFirstOfWeek = (date) => {
    const day = date.getDay();
    const diff = date.getDate() - day;
    return new Date(date.setDate(diff));
  };
  var getFirstOfMonth = (date) => new Date(date.getFullYear(), date.getMonth(), 1);
  function HomeDashboard(el, {
    prevMonth,
    nextMonth,
    prevWeek,
    nextWeek,
    loadingClass = "is-loading"
  }) {
    const [
      currentMonth,
      currentWeek
    ] = el.querySelectorAll("div > nav > span");
    const [
      prevActivities,
      nextActivities,
      prevReminders,
      nextReminders
    ] = el.querySelectorAll("div > nav > button");
    const [
      activities,
      reminders
    ] = [el.firstElementChild, el.lastElementChild];
    function getActivitiesByMonth(month) {
      activities.classList.add(loadingClass);
      prevActivities.disabled = true;
      nextActivities.disabled = true;
      fetch(`/json/activities-listing?${new URLSearchParams({
        month
      })}`, {
        headers: { Accept: "application/json" }
      }).then((res) => res.json().then((json) => ({
        status: res.status,
        ...json
      }))).then(({
        status,
        markup = "",
        prevMonth: p = "",
        nextMonth: n = ""
      }) => {
        if (status !== 200)
          return;
        const firstOfMonth = getFirstOfMonth(new Date(month));
        activities.classList.remove(loadingClass);
        prevActivities.disabled = false;
        nextActivities.disabled = false;
        prevMonth = p;
        nextMonth = n;
        currentMonth.textContent = `${firstOfMonth.toLocaleString("default", { month: "long" })} ${firstOfMonth.getFullYear()}`;
        activities.querySelector("ul").outerHTML = markup.trim();
      });
    }
    function getRemindersByWeek(week) {
      reminders.classList.add(loadingClass);
      prevReminders.disabled = true;
      nextReminders.disabled = true;
      fetch(`/json/reminders-listing?${new URLSearchParams({
        week
      })}`, {
        headers: { Accept: "application/json" }
      }).then((res) => res.json().then((json) => ({
        status: res.status,
        ...json
      }))).then(({
        status,
        markup = "",
        prevWeek: p = "",
        nextWeek: n = ""
      }) => {
        if (status !== 200)
          return;
        const firstOfWeek = getFirstOfWeek(new Date(week));
        reminders.classList.remove(loadingClass);
        prevReminders.disabled = false;
        nextReminders.disabled = false;
        prevWeek = p;
        nextWeek = n;
        currentWeek.textContent = `Week of ${firstOfWeek.toLocaleDateString()}`;
        reminders.querySelector("ul").outerHTML = markup.trim();
      });
    }
    prevActivities.onclick = () => {
      getActivitiesByMonth(prevMonth);
    };
    nextActivities.onclick = () => {
      getActivitiesByMonth(nextMonth);
    };
    prevReminders.onclick = () => {
      getRemindersByWeek(prevWeek);
    };
    nextReminders.onclick = () => {
      getRemindersByWeek(nextWeek);
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
  var loadScript = (src, cb) => {
    const script = document.createElement("script");
    const head = document.querySelector("head");
    script.src = src;
    script.onload = cb;
    head.appendChild(script);
  };

  // src/scripts/components/modal.js
  function Modal(el, {
    activeClass = "is-active",
    actions: actions2,
    events,
    refresh
  }) {
    const close = el.querySelector("button");
    const content = el.querySelector("div");
    let currentContent = "";
    function handleKeyup({ key }) {
      if (key === "Escape") {
        events.emit(actions2.closeModal);
      }
    }
    function handleOpenModal() {
      events.emit(actions2.lockScroll);
      el.classList.add(activeClass);
      document.addEventListener("keyup", handleKeyup);
    }
    function handleCloseModal() {
      events.emit(actions2.unlockScroll);
      el.classList.remove(activeClass);
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

  // src/scripts/components/youtube-video.js
  var SRC = "https://www.youtube.com/iframe_api";
  function YoutubeVideo(el, {
    videoId,
    actions: actions2,
    events
  }) {
    function initPlayer() {
      const player = new YT.Player(el, {
        videoId,
        playerVars: {
          autoplay: 1,
          rel: 0
        }
      });
      events.on(actions2.openModal, () => {
        if (player.playVideo)
          player.playVideo();
      });
      events.on(actions2.closeModal, () => {
        if (player.pauseVideo)
          player.pauseVideo();
      });
      player.addEventListener("onReady", () => {
        player.playVideo();
      });
    }
    if (!document.querySelector(`[src="${SRC}"]`)) {
      window.onYouTubeIframeAPIReady = initPlayer;
      loadScript(SRC);
    } else {
      initPlayer();
    }
  }

  // src/scripts/index.js
  var classMap = {
    "cards-listing": CardsListing,
    form: Form,
    "form-field": FormField,
    "home-dashboard": HomeDashboard,
    modal: Modal,
    "youtube-video": YoutubeVideo
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
        if (link.matches('[href*="youtube.com"]')) {
          e.preventDefault();
          const videoId = link.href.split("v=")[1];
          fetch(`/json/youtube-video?${new URLSearchParams({ videoId })}`, {
            headers: { Accept: "application/json" }
          }).then((res) => res.json().then((json) => ({
            status: res.status,
            ...json
          }))).then(({
            status,
            markup = ""
          }) => {
            switch (status) {
              case 500:
                break;
              case 400:
                break;
              case 200:
              default:
                events.emit(actions.loadModal, { markup });
            }
          });
        }
      });
      if (window.location.hash) {
        const target = document.querySelector(window.location.hash);
        if (target) {
          target.scrollIntoView({ behavior: "smooth" });
        }
      }
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
    components_default({ classMap, actions, cb });
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", handleDOMConentLoaded);
  } else {
    handleDOMConentLoaded();
  }
})();
//# sourceMappingURL=index.js.map
