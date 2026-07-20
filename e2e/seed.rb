# frozen_string_literal: true

# Seeds the E2E Postal install with everything the suite needs. Postal has
# no admin API, so this runs inside the container via `rails runner` — the
# same models the web UI drives, provisioned deterministically.
#
# Idempotent: safe to run repeatedly.

API_KEY = ENV.fetch("E2E_API_KEY", "e2e-api-key-0123456789abcdef")
SMTP_KEY = ENV.fetch("E2E_SMTP_KEY", "e2e-smtp-key-0123456789abcdef")
CAPTURE = ENV.fetch("E2E_CAPTURE_URL", "http://host.docker.internal:8085")

user = User.find_or_create_by!(email_address: "e2e@example.com") do |u|
  u.first_name = "E2E"
  u.last_name = "Bot"
  u.password = "e2e-password-123!"
  u.admin = true
end

org = Organization.find_or_create_by!(permalink: "e2e") do |o|
  o.name = "E2E"
  o.owner = user
  o.time_zone = "UTC"
end

OrganizationUser.find_or_create_by!(organization: org, user: user) do |ou|
  ou.admin = true
  ou.all_servers = true
end

server = org.servers.find_by(permalink: "main") ||
  org.servers.create!(name: "Main", permalink: "main", mode: "Live")

# Credential#generate_key overwrites the key on create, so the
# deterministic test keys are applied afterwards via update_column.
def credential!(server, type, name, key)
  credential = server.credentials.find_by(name: name) ||
    server.credentials.create!(type: type, name: name)
  credential.update_column(:key, key) unless credential.key == key
end

credential!(server, "API", "e2e-api", API_KEY)
credential!(server, "SMTP", "e2e-smtp", SMTP_KEY)

def verified_domain!(server, name)
  server.domains.find_by(name: name) || server.domains.create!(
    name: name,
    verification_method: "DNS",
    verified_at: Time.now,
    dns_checked_at: Time.now,
    spf_status: "OK",
    dkim_status: "OK",
    mx_status: "OK",
    return_path_status: "OK"
  )
end

verified_domain!(server, "e2e.example.com")
inbound_domain = verified_domain!(server, "inbound.e2e.example.com")

Webhook.find_or_create_by!(server: server, name: "e2e-capture") do |w|
  w.url = "#{CAPTURE}/capture/webhook"
  w.all_events = true
  w.enabled = true
  w.sign = true
end

endpoint = server.http_endpoints.find_by(name: "e2e-inbound") ||
  server.http_endpoints.create!(
    name: "e2e-inbound",
    url: "#{CAPTURE}/capture/inbound",
    encoding: "BodyAsJSON",
    format: "Hash",
    strip_replies: false,
    include_attachments: true,
    timeout: 10
  )

server.routes.find_by(name: "*", domain: inbound_domain) ||
  server.routes.create!(
    name: "*",
    domain: inbound_domain,
    endpoint: endpoint,
    spam_mode: "Mark",
    mode: "Endpoint"
  )

puts "seeded: org=e2e server=main api_key=#{API_KEY}"
