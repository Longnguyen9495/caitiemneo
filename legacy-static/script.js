document.documentElement.classList.add("js");

const menuToggle = document.querySelector("[data-menu-toggle]");
const menu = document.querySelector("[data-menu]");
const menuLabel = menuToggle?.querySelector(".sr-only");
const header = document.querySelector("[data-header]");
const mobileMenu = window.matchMedia("(max-width: 760px)");

function closeMenu({ returnFocus = false } = {}) {
  if (!menuToggle || !menu || !menuLabel) return;

  menuToggle.setAttribute("aria-expanded", "false");
  menuToggle.setAttribute("aria-label", "Mở menu");
  menuLabel.textContent = "Mở menu";
  menu.classList.remove("is-open");

  if (returnFocus) menuToggle.focus();
}

function openMenu() {
  if (!menuToggle || !menu || !menuLabel) return;

  menuToggle.setAttribute("aria-expanded", "true");
  menuToggle.setAttribute("aria-label", "Đóng menu");
  menuLabel.textContent = "Đóng menu";
  menu.classList.add("is-open");
}

if (menuToggle && menu && menuLabel) {
  menuToggle.addEventListener("click", () => {
    const isOpen = menuToggle.getAttribute("aria-expanded") === "true";
    if (isOpen) {
      closeMenu();
    } else {
      openMenu();
    }
  });

  menu.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => closeMenu());
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && menuToggle.getAttribute("aria-expanded") === "true") {
      closeMenu({ returnFocus: true });
    }
  });

  document.addEventListener("click", (event) => {
    if (!mobileMenu.matches || menuToggle.getAttribute("aria-expanded") !== "true") return;
    if (!menu.contains(event.target) && !menuToggle.contains(event.target)) closeMenu();
  });

  const closeMenuOnDesktop = () => {
    if (!mobileMenu.matches) closeMenu();
  };

  mobileMenu.addEventListener("change", closeMenuOnDesktop);
}

if (header) {
  const updateHeader = () => header.classList.toggle("is-scrolled", window.scrollY > 8);
  updateHeader();
  window.addEventListener("scroll", updateHeader, { passive: true });
}

const revealedItems = document.querySelectorAll("[data-reveal]");
const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

if (!reducedMotion.matches && "IntersectionObserver" in window) {
  const observer = new IntersectionObserver(
    (entries, activeObserver) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        activeObserver.unobserve(entry.target);
      });
    },
    { threshold: 0.12 },
  );

  revealedItems.forEach((item) => observer.observe(item));
} else {
  revealedItems.forEach((item) => item.classList.add("is-visible"));
}
