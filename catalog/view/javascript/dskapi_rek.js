function dskapiChangeContainer() {
  const dskapi_label_container = document.getElementsByClassName(
    'dskapi-label-container'
  )[0];
  if (dskapi_label_container.style.visibility == 'visible') {
    dskapi_label_container.style.visibility = 'hidden';
    dskapi_label_container.style.opacity = 0;
    dskapi_label_container.style.transition =
      'visibility 0s, opacity 0.5s ease';
  } else {
    dskapi_label_container.style.visibility = 'visible';
    dskapi_label_container.style.opacity = 1;
  }
}
