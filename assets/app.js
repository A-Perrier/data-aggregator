import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

document.addEventListener('DOMContentLoaded', function () {
    const searchbar = document.getElementById('searchbar');
    const aggregatorBtn = document.getElementById('aggregator-button');
    let articles = document.querySelectorAll('.aggregated-article');

    const fetchArticles = async () => {
        const response = await fetch('/aggregate');
        return await response.json();
    }

    const registerArticlesActions = async () => {
        const deleteBtns = document.querySelectorAll('.delete-article');
        deleteBtns.forEach((deleteBtn, i) => {
            deleteBtn.addEventListener('click', async e => {
                const articleId = deleteBtn.getAttribute('data-id');
                const response = await fetch(
                    `/api/articles/${articleId}`,{ method: 'DELETE' });
                const result = await response.json();
                if (result.success === true) {
                    deleteBtn.closest('.card').remove();
                    // articles = document.querySelectorAll('.aggregated-article');
                }
            })
        })
    }

    const searchArticles = () => {
        const search = searchbar.value.toLowerCase();
        // Filter function can't be applied on a Nodelist, so we're making a regular array of it
        const filteredNodeList = document.createDocumentFragment();
        const arrayArticles = [...articles].map(article => {
            const title = article.querySelector('.card-title').textContent.toLowerCase();
            const description = article.querySelector('.card-text').textContent.toLowerCase();
            (title.includes(search) || description.includes(search)) ? filteredNodeList.appendChild(article.cloneNode(true)) : false;
        })
        return filteredNodeList.childNodes;
    }


    aggregatorBtn.addEventListener('click', async function (e) {
        e.preventDefault();
        const response = await fetchArticles();
        if (response.status === 'success') {
            document.querySelector('.articles-container .cards-container').innerHTML = response.data;
            // We need to trigger events again, to ensure new elements may trigger buttons
            registerArticlesActions();
            // Refresh articles list
            articles = document.querySelectorAll('.aggregated-article');
        }
    })

    searchbar.addEventListener('keyup', e => {
        const articlesContainer = document.querySelector('.articles-container .cards-container');
        articlesContainer.innerHTML = '';
        // We reinject all elements from the NodeList
        [].forEach.call(searchArticles(), (item) => articlesContainer.appendChild(item));
        registerArticlesActions()
    })

    // First launch at initial page loading
    registerArticlesActions();
})