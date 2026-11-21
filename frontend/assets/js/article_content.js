(() => {
  const imageExtension = /\.(?:avif|bmp|gif|jpe?g|png|svg|webp)$/i;
  const contentUrl = /(?:https?:\/\/[^\s<>"']+|\/api\/media\/[a-f0-9]{24})/gi;

  function isImageUrl(url, knownImages) {
    if (knownImages.has(url)) return true;
    const pathname = new URL(url).pathname;
    return imageExtension.test(pathname) || /^\/api\/media\/[a-f0-9]{24}$/i.test(pathname);
  }

  function createImage(url, title) {
    const image = document.createElement("img");
    image.src = url;
    image.alt = title;
    image.loading = "lazy";
    return image;
  }

  function render(container, content, title = "", media = []) {
    const knownImages = new Set((Array.isArray(media) ? media : [])
      .filter((item) => item.type === "image")
      .map((item) => App.safeUrl(item.url || ""))
      .filter(Boolean));
    const renderedImages = new Set();
    const text = String(content || "");
    const fragment = document.createDocumentFragment();
    let cursor = 0;
    let match;

    contentUrl.lastIndex = 0;
    while ((match = contentUrl.exec(text)) !== null) {
      let url = match[0];
      let suffix = "";
      while (url && ".,!?;:)]}".includes(url[url.length - 1])) {
        suffix = url[url.length - 1] + suffix;
        url = url.slice(0, -1);
      }

      fragment.append(document.createTextNode(text.slice(cursor, match.index)));
      const safeUrl = App.safeUrl(url);
      if (!safeUrl) {
        fragment.append(document.createTextNode(url));
      } else if (isImageUrl(safeUrl, knownImages)) {
        const imageBlock = document.createElement("div");
        imageBlock.className = "article-media-list";
        imageBlock.append(createImage(safeUrl, title));
        fragment.append(imageBlock);
        renderedImages.add(safeUrl);
      } else {
        const link = document.createElement("a");
        link.href = safeUrl;
        link.target = "_blank";
        link.rel = "noopener noreferrer";
        link.textContent = url;
        fragment.append(link);
      }
      if (suffix) fragment.append(document.createTextNode(suffix));
      cursor = match.index + match[0].length;
    }
    fragment.append(document.createTextNode(text.slice(cursor)));
    container.replaceChildren(fragment);

    let remainingMedia = null;
    const mediaContainer = () => {
      if (!remainingMedia) {
        remainingMedia = document.createElement("div");
        remainingMedia.className = "article-media-list";
      }
      return remainingMedia;
    };

    for (const item of Array.isArray(media) ? media : []) {
      const safeUrl = App.safeUrl(item.url || "");
      if (!safeUrl) continue;
      if (item.type === "image") {
        if (renderedImages.has(safeUrl)) continue;
        mediaContainer().append(createImage(safeUrl, title));
        renderedImages.add(safeUrl);
      } else if (item.type === "video") {
        const video = document.createElement("video");
        video.src = safeUrl;
        video.controls = true;
        video.preload = "metadata";
        mediaContainer().append(video);
      }
    }
    if (remainingMedia?.childElementCount) container.append(remainingMedia);
  }

  App.articleContent = { render };
})();
