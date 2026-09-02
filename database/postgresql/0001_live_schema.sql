--
-- PostgreSQL database dump
--


-- Dumped from database version 18.6 (Ubuntu 18.6-0ubuntu0.26.04.1)
-- Dumped by pg_dump version 18.6 (Ubuntu 18.6-0ubuntu0.26.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;


--
-- Name: career_facts_category_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.career_facts_category_t AS ENUM (
    'achievement',
    'responsibility',
    'project',
    'leadership',
    'commercial',
    'technical',
    'other'
);


--
-- Name: career_facts_source_type_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.career_facts_source_type_t AS ENUM (
    'cv',
    'user'
);


--
-- Name: career_questions_status_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.career_questions_status_t AS ENUM (
    'open',
    'answered',
    'dismissed'
);


--
-- Name: cv_documents_generation_focus_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.cv_documents_generation_focus_t AS ENUM (
    'balanced',
    'management',
    'technical',
    'impact'
);


--
-- Name: cv_documents_source_mode_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.cv_documents_source_mode_t AS ENUM (
    'upload',
    'career'
);


--
-- Name: document_metadata_document_type_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.document_metadata_document_type_t AS ENUM (
    'master_cv',
    'tailored_cv'
);


--
-- Name: job_applications_status_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.job_applications_status_t AS ENUM (
    'interested',
    'preparing',
    'applied',
    'interview',
    'offer',
    'accepted',
    'rejected',
    'withdrawn'
);


--
-- Name: tailored_cvs_cv_framework_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tailored_cvs_cv_framework_t AS ENUM (
    'experience_led',
    'profile_led',
    'skills_led',
    'hybrid'
);


--
-- Name: tailored_cvs_generation_mode_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tailored_cvs_generation_mode_t AS ENUM (
    'local_fallback',
    'openai'
);


--
-- Name: tailored_cvs_source_mode_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tailored_cvs_source_mode_t AS ENUM (
    'cv',
    'career'
);


--
-- Name: tailored_cvs_tailoring_focus_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tailored_cvs_tailoring_focus_t AS ENUM (
    'balanced',
    'management',
    'technical',
    'impact'
);


--
-- Name: tailored_cvs_tailoring_tone_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.tailored_cvs_tailoring_tone_t AS ENUM (
    'professional',
    'formal',
    'concise',
    'approachable'
);


--
-- Name: user_preferences_theme_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.user_preferences_theme_t AS ENUM (
    'system',
    'light',
    'dark'
);


--
-- Name: webauthn_challenges_ceremony_t; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.webauthn_challenges_ceremony_t AS ENUM (
    'registration',
    'authentication'
);


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: career_fact_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.career_fact_sources (
    user_id bigint,
    fact_id bigint,
    source_cv_id bigint,
    source_document_name character varying(255),
    source_excerpt text,
    created_at timestamp with time zone
);


--
-- Name: career_facts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.career_facts (
    id bigint,
    user_id bigint,
    role_id bigint,
    category public.career_facts_category_t,
    fact_text text,
    fact_hash character(64),
    source_type public.career_facts_source_type_t,
    source_cv_id bigint,
    source_document_name character varying(255),
    source_excerpt text,
    user_confirmed smallint,
    archived_at timestamp without time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: career_imports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.career_imports (
    id bigint,
    user_id bigint,
    source_cv_id bigint,
    model_name character varying(191),
    role_count integer,
    fact_count integer,
    question_count integer,
    created_at timestamp with time zone
);


--
-- Name: career_questions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.career_questions (
    id bigint,
    user_id bigint,
    role_id bigint,
    question_text text,
    rationale character varying(500),
    question_hash character(64),
    status public.career_questions_status_t,
    answered_fact_id bigint,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: career_role_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.career_role_sources (
    user_id bigint,
    role_id bigint,
    source_cv_id bigint,
    source_document_name character varying(255),
    source_excerpt text,
    created_at timestamp with time zone
);


--
-- Name: career_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.career_roles (
    id bigint,
    user_id bigint,
    employer_name character varying(255),
    job_title character varying(255),
    location character varying(255),
    start_date_text character varying(80),
    end_date_text character varying(80),
    is_current smallint,
    summary text,
    display_order integer,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: cv_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cv_documents (
    id bigint,
    user_id bigint,
    original_filename character varying(255),
    mime_type character varying(127),
    original_docx bytea,
    extracted_text text,
    version_number integer,
    source_mode public.cv_documents_source_mode_t,
    career_snapshot text,
    generation_focus public.cv_documents_generation_focus_t,
    generation_summary text,
    profile_snapshot text,
    created_at timestamp with time zone
);


--
-- Name: document_metadata; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.document_metadata (
    user_id bigint,
    document_type public.document_metadata_document_type_t,
    document_id bigint,
    display_name character varying(255),
    archived_at timestamp without time zone,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: job_applications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_applications (
    id bigint,
    user_id bigint,
    company_name character varying(255),
    job_title character varying(255),
    location character varying(255),
    source_url character varying(1000),
    status public.job_applications_status_t,
    application_date date,
    follow_up_date date,
    cv_document_id bigint,
    tailored_cv_id bigint,
    notes text,
    created_at timestamp with time zone,
    updated_at timestamp with time zone
);


--
-- Name: schema_migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.schema_migrations (
    filename character varying(255),
    applied_at timestamp with time zone
);


--
-- Name: sent_document_emails; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sent_document_emails (
    id bigint,
    user_id bigint,
    tailored_cv_id bigint,
    recipient_email character varying(255),
    included_cv smallint,
    included_cover_letter smallint,
    created_at timestamp with time zone
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character(64),
    user_id bigint,
    expires_at timestamp without time zone,
    created_at timestamp with time zone
);


--
-- Name: site_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.site_settings (
    setting_key character varying(191),
    setting_value text,
    updated_at timestamp with time zone
);


--
-- Name: tailored_cvs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tailored_cvs (
    id bigint,
    user_id bigint,
    source_cv_id bigint,
    source_mode public.tailored_cvs_source_mode_t,
    career_snapshot text,
    job_description text,
    tailored_text text,
    change_summary text,
    generation_mode public.tailored_cvs_generation_mode_t,
    created_at timestamp with time zone,
    company_name character varying(255),
    job_title character varying(255),
    model_name character varying(191),
    cover_letter_text text,
    parent_tailored_cv_id bigint,
    revision_group_key character varying(64),
    revision_number integer,
    tailoring_focus public.tailored_cvs_tailoring_focus_t,
    tailoring_tone public.tailored_cvs_tailoring_tone_t,
    cv_framework public.tailored_cvs_cv_framework_t,
    tailoring_notes character varying(500)
);


--
-- Name: user_preferences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_preferences (
    user_id bigint,
    ai_model character varying(191),
    theme public.user_preferences_theme_t,
    updated_at timestamp with time zone
);


--
-- Name: user_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_profiles (
    user_id bigint,
    full_name character varying(255),
    phone character varying(80),
    address_line_1 character varying(255),
    address_line_2 character varying(255),
    city character varying(120),
    region character varying(120),
    postal_code character varying(40),
    country character varying(120),
    linkedin_url character varying(500),
    portfolio_url character varying(500),
    updated_at timestamp with time zone
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint,
    email character varying(255),
    password_hash character varying(255),
    created_at timestamp with time zone,
    updated_at timestamp with time zone,
    webauthn_user_id bytea
);


--
-- Name: webauthn_challenges; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webauthn_challenges (
    id bigint,
    token_hash character(64),
    ceremony public.webauthn_challenges_ceremony_t,
    challenge character varying(512),
    email character varying(255),
    user_id bigint,
    user_handle bytea,
    request_ip_hash character(64),
    expires_at timestamp without time zone,
    consumed_at timestamp without time zone,
    created_at timestamp with time zone
);


--
-- Name: webauthn_credentials; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.webauthn_credentials (
    credential_id character varying(512),
    user_id bigint,
    credential_public_key bytea,
    signature_counter bigint,
    transports character varying(255),
    device_type character varying(32),
    backed_up smallint,
    created_at timestamp with time zone,
    last_used_at timestamp without time zone
);


ALTER TABLE public.users ADD PRIMARY KEY (id), ADD CONSTRAINT users_email_unique UNIQUE (email);
ALTER TABLE public.site_settings ADD PRIMARY KEY (setting_key);
ALTER TABLE public.sessions ADD PRIMARY KEY (id);
ALTER TABLE public.cv_documents ADD PRIMARY KEY (id), ADD CONSTRAINT cv_documents_user_version UNIQUE (user_id, version_number);
ALTER TABLE public.tailored_cvs ADD PRIMARY KEY (id), ADD CONSTRAINT tailored_cvs_user_revision_version UNIQUE (user_id, revision_group_key, revision_number);
ALTER TABLE public.user_profiles ADD PRIMARY KEY (user_id);
ALTER TABLE public.user_preferences ADD PRIMARY KEY (user_id);
ALTER TABLE public.job_applications ADD PRIMARY KEY (id);
ALTER TABLE public.sent_document_emails ADD PRIMARY KEY (id);
ALTER TABLE public.document_metadata ADD PRIMARY KEY (user_id, document_type, document_id);
ALTER TABLE public.career_roles ADD PRIMARY KEY (id);
ALTER TABLE public.career_role_sources ADD PRIMARY KEY (user_id, role_id, source_cv_id);
ALTER TABLE public.career_facts ADD PRIMARY KEY (id), ADD CONSTRAINT career_facts_user_role_hash UNIQUE (user_id, role_id, fact_hash);
ALTER TABLE public.career_fact_sources ADD PRIMARY KEY (user_id, fact_id, source_cv_id);
ALTER TABLE public.career_questions ADD PRIMARY KEY (id), ADD CONSTRAINT career_questions_user_role_hash UNIQUE (user_id, role_id, question_hash);
ALTER TABLE public.career_imports ADD PRIMARY KEY (id);
ALTER TABLE public.webauthn_credentials ADD PRIMARY KEY (credential_id);
ALTER TABLE public.webauthn_challenges ADD PRIMARY KEY (id), ADD CONSTRAINT webauthn_challenges_token_unique UNIQUE (token_hash);

ALTER TABLE public.users ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.cv_documents ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.tailored_cvs ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.job_applications ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.sent_document_emails ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.career_roles ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.career_facts ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.career_questions ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.career_imports ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;
ALTER TABLE public.webauthn_challenges ALTER COLUMN id ADD GENERATED BY DEFAULT AS IDENTITY;

CREATE INDEX sessions_user_id_idx ON public.sessions (user_id);
CREATE INDEX tailored_cvs_user_created_idx ON public.tailored_cvs (user_id, created_at);
CREATE INDEX job_applications_user_status_idx ON public.job_applications (user_id, status);
CREATE INDEX career_roles_user_order_idx ON public.career_roles (user_id, display_order, id);
CREATE INDEX career_facts_user_active_idx ON public.career_facts (user_id, archived_at);
CREATE INDEX career_questions_user_status_idx ON public.career_questions (user_id, status);
CREATE INDEX webauthn_credentials_user_id_idx ON public.webauthn_credentials (user_id);

-- PostgreSQL database dump complete

