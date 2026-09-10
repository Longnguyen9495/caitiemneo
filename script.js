document.documentElement.classList.add("js");

const menuToggle = document.querySelector("[data-menu-toggle]");
const menu = document.querySelector("[data-menu]");
const menuLabel = menuToggle?.querySelector(".sr-only");

function closeMenu() {
  if (!menuToggle || !menu || !menuLabel) return;

  menuToggle.setAttribute("aria-expanded", "false");
  menuToggle.setAttribute("aria-label", "Mở menu");
  menuLabel.textContent = "Mở menu";
  menu.classList.remove("is-open");
}

if (menuToggle && menu && menuLabel) {
  menuToggle.addEventListener("click", () => {
    const isOpen = menuToggle.getAttribute("aria-expanded") === "true";

    if (isOpen) {
      closeMenu();
      return;
    }

    menuToggle.setAttribute("aria-expanded", "true");
    menuToggle.setAttribute("aria-label", "Đóng menu");
    menuLabel.textContent = "Đóng menu";
    menu.classList.add("is-open");
  });

  menu.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", closeMenu);
  });

  window.addEventListener("resize", () => {
    if (window.matchMedia("(min-width: 761px)").matches) closeMenu();
  });
}

const revealedItems = document.querySelectorAll(".reveal");

if ("IntersectionObserver" in window) {
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
