document.getElementById('edit-categories').addEventListener('change', showHide)
showHide()

function showHide() {  
  if (document.getElementById('edit-categories').value == 'fixed_categories') {    
    document.getElementsByClassName('js-form-item-category-id')[0].classList.remove('hidden')
    document.getElementsByClassName('js-form-item-category-field')[0].classList.add('hidden')
  } else {    
    document.getElementsByClassName('js-form-item-category-id')[0].classList.add('hidden')
    document.getElementsByClassName('js-form-item-category-field')[0].classList.remove('hidden')
  }
}
