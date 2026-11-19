<div class="reapit-properties container py-5">
    <div class="row">
        <div class="col-12">
            <header class="reapit-properties__header mb-4">
        <h1>$Title.XML</h1>
        <% if $Content %><div class="reapit-properties__intro">$Content</div><% end_if %>
    </header>

            <form class="reapit-properties__sort mb-4" method="get" action="$Link.ATT">
        <label for="reapit-sort">Sort properties</label>
        <select id="reapit-sort" name="sort">
            <option value="newest"<% if $Sort == 'newest' %> selected<% end_if %>>Newest first</option>
            <option value="oldest"<% if $Sort == 'oldest' %> selected<% end_if %>>Oldest first</option>
            <option value="price-low"<% if $Sort == 'price-low' %> selected<% end_if %>>Lowest rent</option>
            <option value="price-high"<% if $Sort == 'price-high' %> selected<% end_if %>>Highest rent</option>
        </select>
        <button type="submit">Apply</button>
    </form>

    <% if $Properties.Count %>
        <div class="row">
            <% loop $Properties %>
            <div class="col-md-6 col-lg-4 mb-4">
                <article class="reapit-property-card h-100">
                    <a href="$Link.ATT" class="reapit-property-card__link" aria-label="View property: $Title.ATT">
                        <div class="reapit-property-card__image">
                            <% if $Images.First.Image %>
                                <img src="$Images.First.Image.Fill(640,420).URL.ATT" alt="" loading="lazy">
                            <% else %>
                                <img src="$resourceURL('dorsetdigital/silverstripe-reapit:client/images/placeholder-640x420.svg')" alt="" loading="lazy" width="640" height="420">
                            <% end_if %>
                        </div>
                        <div class="reapit-property-card__body">
                            <h2>$Title.XML</h2>
                            <% if $Address %><p>$Address.XML</p><% end_if %>
                            <% if $Rent %><p class="reapit-property-card__rent">&pound;$Rent.Nice <% if $RentFrequency %>/ $RentFrequency.XML<% end_if %></p><% end_if %>
                            <p>
                                <% if $Bedrooms %>$Bedrooms bedroom<% if $Bedrooms != 1 %>s<% end_if %><% end_if %>
                                <% if $Bathrooms %> · $Bathrooms bathroom<% if $Bathrooms != 1 %>s<% end_if %><% end_if %>
                            </p>
                            <span class="reapit-property-card__cta">View property</span>
                        </div>
                    </a>
                </article>
            </div>
            <% end_loop %>
        </div>
        <% with $Properties %>
            <% if $MoreThanOnePage %>
                <nav class="reapit-properties__pagination" aria-label="Property pages">
                    <% if $NotFirstPage %><a href="$PrevLink.ATT">Previous</a><% end_if %>
                    <span>Page $CurrentPage of $TotalPages</span>
                    <% if $NotLastPage %><a href="$NextLink.ATT">Next</a><% end_if %>
                </nav>
            <% end_if %>
        <% end_with %>
    <% else %>
        <p>There are currently no available properties.</p>
    <% end_if %>
        </div>
    </div>
</div>
