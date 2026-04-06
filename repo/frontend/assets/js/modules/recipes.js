/**
 * PantryPilot Recipes Module
 * Search, list, create, detail rendering.
 */
(function () {
  const { apiRequest, escapeHtml, guardSubmit, releaseSubmit, inputValue, numberInput } = window.PantryPilot;

  let selectedRecipeId = 0;

  function recipeDifficultyClass(difficulty) {
    const value = String(difficulty || "easy").toLowerCase();
    if (value === "hard") return "hard";
    if (value === "medium") return "medium";
    return "easy";
  }

  function renderRecipeGrid(items) {
    const grid = document.getElementById("recipesGrid");
    if (!items || items.length === 0) {
      grid.innerHTML = `<div class="empty-state">No recipes found for the current filters.</div>`;
      return;
    }
    grid.innerHTML = items.map((item) => `
      <article class="recipe-card" data-id="${escapeHtml(item.id)}">
        <h3>${escapeHtml(item.name || "Unnamed recipe")}</h3>
        <div class="recipe-meta">
          <span><i class="layui-icon layui-icon-time"></i> ${escapeHtml(item.prep_minutes || 0)} min</span>
          <span class="difficulty ${recipeDifficultyClass(item.difficulty)}">${escapeHtml(item.difficulty || "easy")}</span>
        </div>
        <div class="recipe-stats">
          <span>${escapeHtml(item.calories || 0)} kcal</span>
          <span>$${Number(item.estimated_cost || 0).toFixed(2)}</span>
        </div>
      </article>
    `).join("");

    grid.querySelectorAll(".recipe-card").forEach((card) => {
      card.addEventListener("click", async () => {
        const id = Number(card.dataset.id || "0");
        if (!id) return;
        selectedRecipeId = id;
        try {
          const detail = await apiRequest(`/bookings/recipe/${id}`);
          layui.layer.open({
            type: 1, title: detail.name || `Recipe #${id}`, area: ["680px", "520px"], shadeClose: true,
            content: `<div class="recipe-modal">
              <p><strong>Description:</strong> ${escapeHtml(detail.description || "N/A")}</p>
              <p><strong>Prep:</strong> ${escapeHtml(detail.prep_minutes || 0)} min | <strong>Difficulty:</strong> ${escapeHtml(detail.difficulty || "easy")}</p>
              <p><strong>Calories:</strong> ${escapeHtml(detail.calories || 0)} | <strong>Estimated cost:</strong> $${Number(detail.estimated_cost || 0).toFixed(2)}</p>
              <p><strong>Ingredients:</strong> ${escapeHtml((detail.ingredients || []).join(", ") || "N/A")}</p>
              <p><strong>Cookware:</strong> ${escapeHtml((detail.cookware || []).join(", ") || "N/A")}</p>
              <p><strong>Allergens:</strong> ${escapeHtml((detail.allergens || []).join(", ") || "N/A")}</p>
            </div>`
          });
        } catch (e) { layui.layer.msg(e.message); }
      });
    });
  }

  async function searchRecipes() {
    const qs = new URLSearchParams();
    const fields = { ingredient: "recipeSearchIngredient", cookware: "recipeSearchCookware", exclude_allergens: "recipeSearchExcludeAllergens", prep_under: "recipeSearchPrep", step_count_max: "recipeSearchStepCount", difficulty: "recipeSearchDifficulty", max_calories: "recipeSearchMaxCalories", max_budget: "recipeSearchBudget", tags: "recipeSearchTags", rank_mode: "recipeSearchRankMode" };
    Object.entries(fields).forEach(([param, id]) => { const v = document.getElementById(id)?.value?.trim(); if (v) qs.set(param, v); });
    const data = await apiRequest(`/recipes/search?${qs.toString()}`);
    const items = data.items || [];
    if (items.length > 0) selectedRecipeId = Number(items[0].id || selectedRecipeId);
    renderRecipeGrid(items);
  }

  function bindRecipeEvents() {
    document.getElementById("btnLoadRecipes").addEventListener("click", async () => {
      const data = await apiRequest("/recipes");
      const items = data.items || [];
      if (items.length > 0) selectedRecipeId = Number(items[0].id || selectedRecipeId);
      renderRecipeGrid(items);
    });
    document.getElementById("btnSearchRecipes").addEventListener("click", async () => {
      try { await searchRecipes(); } catch (e) { layui.layer.msg(e.message); }
    });
    document.getElementById("btnCreateRecipe").addEventListener("click", async () => {
      const btn = document.getElementById("btnCreateRecipe");
      if (!guardSubmit("createRecipe", btn)) return;
      try {
        const name = inputValue("recipeName"); if (!name) throw new Error("recipeName is required");
        const description = inputValue("recipeDescription"); if (!description) throw new Error("recipeDescription is required");
        const ingredientTerms = inputValue("recipeIngredients").split(",").map(v => v.trim()).filter(Boolean);
        const cookwareTerms = inputValue("recipeCookware").split(",").map(v => v.trim()).filter(Boolean);
        const allergenTerms = inputValue("recipeAllergens").split(",").map(v => v.trim()).filter(Boolean);
        if (!ingredientTerms.length) throw new Error("recipeIngredients is required");
        if (!cookwareTerms.length) throw new Error("recipeCookware is required");
        if (!allergenTerms.length) throw new Error("recipeAllergens is required");
        await apiRequest("/recipes", "POST", {
          name, description, prep_minutes: numberInput("recipePrepMinutes", true), step_count: numberInput("recipeStepCount", true),
          servings: numberInput("recipeServings", true), difficulty: inputValue("recipeDifficultyCreate") || (() => { throw new Error("difficulty required"); })(),
          calories: numberInput("recipeCalories", true), estimated_cost: numberInput("recipeEstimatedCost", true),
          ingredients: ingredientTerms, cookware: cookwareTerms, allergens: allergenTerms,
          status: inputValue("recipeStatus") || (() => { throw new Error("status required"); })()
        });
        layui.layer.msg("Recipe created");
      } catch (e) { layui.layer.msg(e.message); } finally { releaseSubmit("createRecipe", btn); }
    });
  }

  window.PantryPilot = Object.assign(window.PantryPilot || {}, {
    bindRecipeEvents, renderRecipeGrid, searchRecipes,
    getSelectedRecipeId: () => selectedRecipeId, setSelectedRecipeId: (id) => { selectedRecipeId = id; },
  });
})();
